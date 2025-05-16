<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Validator;
use App\Models\DayOff;
use App\Models\Leave;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:exportPDF');
    }

    /**
     * Get all data report
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function index(Request $request)
    {
        $users = User::query();
        // Search: keyword (ID, fullname, email), status
        if ($request->filled('keyword')) {
            $keyword = $request->query('keyword');
            $users->where(function ($query) use ($keyword) {
                $query->where('idkey', 'LIKE', "%{$keyword}%")
                    ->orWhere('fullname', 'LIKE', "%{$keyword}%")
                    ->orWhere('email', 'LIKE', "%{$keyword}%");
            });
        }

        if ($request->filled('status')) {
            $status = explode(",", $request->query('status'));
            if (!in_array('-1', $status)) {
                $users->whereIn('m_users.status', $status);
            }
        } else {
            $users->whereIn('m_users.status', [STATUS_IS_ACTIVE]);
        }

        // Sort
        if ($request->filled('sort_by') || $request->filled('sort_order')) {
            $validSortColumns = ['id', 'idkey', 'employee_name', 'status', 'status_working', 'role', 'email', 'time_off_hours', 'leader_name'];

            $sortBy = $request->query('sort_by', 'idkey');
            $sortOrder = $request->query('sort_order', 'asc');

            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'idkey';
            }

            $sortOrder = $sortOrder === 'desc' ? 'desc' : 'asc';

            if ($sortBy === "employee_name") {
                $users = $users->orderByRaw("SUBSTRING_INDEX(fullname, ' ', -1) $sortOrder");
            } elseif ($sortBy == "role") {
                $users = $users
                    ->join('r_model_has_roles', 'm_users.id', '=', 'r_model_has_roles.model_id')
                    ->join('m_roles', 'r_model_has_roles.role_id', '=', 'm_roles.id')
                    ->select('m_users.*')
                    ->distinct()
                    ->orderByRaw("m_roles.name $sortOrder");
            } elseif ($sortBy === "email") {
                $users = $users->orderByRaw("LENGTH(email) $sortOrder");
            } elseif ($sortBy === "time_off_hours") {
                $users = $users->orderByRaw("time_off_hours $sortOrder");
            } elseif ($sortBy === "leader_name") {
                $users->leftJoin('m_users as leaders', 'm_users.leader_id', '=', 'leaders.id')
                    ->orderByRaw("SUBSTRING_INDEX(leaders.fullname, ' ', -1) $sortOrder")
                    ->select('m_users.*');
            } elseif ($sortBy === "status") {
                $users = $users->orderByRaw("status $sortOrder");
            } elseif ($sortBy === "status_working") {
                $users = $users->orderByRaw("status_working $sortOrder");
            } else {
                $users = $users->orderByRaw("CAST(SUBSTRING(idkey, 3) AS UNSIGNED) $sortOrder");
            }
        } else {
            $users = $users->orderByRaw("CAST(SUBSTRING(idkey, 3) AS UNSIGNED) ASC");
        }
        $limit = $request->filled('limit') ? $request->get('limit') : DEFAULT_PAGE_SIZE;
        if ($limit > MAX_DEFAULT_PAGE_SIZE) {
            $limit = MAX_DEFAULT_PAGE_SIZE;
        }
        // Pagination
        $users = $users->paginate($limit);
        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => UserResource::collection($users),
            'total'   => $users->total(),
        ], SUCCESS);
    }

    /**
     * Export PDF
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function export(Request $request)
    {
        if ($request->filled('month')) {
            $month = $request->input('month');
        } else {
            $month = Carbon::now()->month;
        }

        if ($request->filled('year')) {
            $year = $request->input('year');
        } else {
            $year = Carbon::now()->year;
        }

        $data = [];
        $validator = Validator::make($request->all(), [
            'items'      => 'required|array',
            'items.*.id' => 'required|integer|exists:m_users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }
        foreach ($request->input('items') as $item) {
            $user = User::where('id', $item['id'])->first();
            $joiningDate = $user->started_at ? Carbon::parse($user->started_at)->format('Y-m-d') : null;
            $terminateDate = $user->ended_at ? Carbon::parse($user->ended_at)->format('Y-m-d') : null;
            // Lấy ngày của 1 tháng (từ ngày 16 tháng trước tới ngày 15 tháng này)
            $totalDays = $this->getDaysOfMonth($month, $year);

            $totalWorkingDays = 0;
            $workingDays = [];
            foreach ($totalDays as $day) {
                $leaveRecord = $user->leaves()
                    ->where('day_leave', $day)
                    ->where('status', STATUS_APPROVAL)
                    ->where('is_delete', DELETED_N)
                    ->first();
                $dayOffRecord = DayOff::where('is_delete', DELETED_N)
                    ->where('day_off', $day)->first();

                $date = Carbon::createFromDate($day);
                $isWeekend = $date->isSaturday() || $date->isSunday();
                $leave = null;
                $dayOffStatus = null;
                $isWorkingDay = false;
                $salary = null;
                $shiftLeave = null;
                if ($date->greaterThanOrEqualTo($joiningDate) && (!$terminateDate || $date->lessThanOrEqualTo($terminateDate))) {
                    if ($dayOffRecord) {
                        $dayOffStatus = $dayOffRecord->status;
                        if ($dayOffStatus == STATUS_DAY_ON_DAY) {
                            $totalWorkingDays++;
                            if($leaveRecord) {
                                $shiftLeave = $leaveRecord->shift ?? null;
                                if ($leaveRecord->shift == SHIFT_ALL_DAY) {
                                    $isWorkingDay = false;
                                    $totalWorkingDays--;
                                } elseif ($leaveRecord->shift == SHIFT_MORNING || $leaveRecord->shift == SHIFT_AFTERNOON) {
                                    $isWorkingDay = true;
                                    $totalWorkingDays -= WORK_HALF_DAY;
                                }
                                $leave = true;
                            } else {
                                $isWorkingDay = true;
                            }
                        } elseif ($dayOffStatus == STATUS_DAY_OFF_DAY) {
                            $isWorkingDay = false;
                            $leave = null;
                        }
                        $salary = PAID_LEAVE;
                    } else {
                        if ($leaveRecord) {
                            if($isWeekend) {
                                $leave = null;
                                $isWorkingDay = false;
                            } else {
                                $leave = true;
                                $shiftLeave = $leaveRecord->shift ?? null;
                                $salary = $leaveRecord->salary;
                                if ($leaveRecord->shift == SHIFT_ALL_DAY) {
                                    $isWorkingDay = false;
                                } elseif ($leaveRecord->shift == SHIFT_MORNING || $leaveRecord->shift == SHIFT_AFTERNOON) {
                                    $isWorkingDay = true;
                                    $totalWorkingDays += WORK_HALF_DAY;
                                }
                            }
                        } else {
                            // Nếu không phải ngày nghỉ hoặc đơn xin nghỉ, xét nếu không phải cuối tuần thì cộng 1
                            if (!$isWeekend) {
                                $isWorkingDay = true;
                                $totalWorkingDays++;
                                $salary = WORK_DAY;
                            } else {
                                $isWorkingDay = false;
                                $salary = OFF_DAY;
                            }
                        }
                    }
                } else {
                    if ($dayOffRecord) {
                        $dayOffStatus = $dayOffRecord->status;
                        if ($dayOffStatus == STATUS_DAY_ON_DAY) {
                            $isWorkingDay = true;
                        } elseif ($dayOffStatus == STATUS_DAY_OFF_DAY) {
                            $isWorkingDay = false;
                        }
                    }
                    else{
                        $isWorkingDay = true;
                    }
                }


                $workingDays[] = [
                    'year'           => $date->year,
                    'month'          => $date->month,
                    'day'            => $day,
                    'day_of_week_jp' => $date->dayOfWeek,
                    'is_working_day' => $isWorkingDay,
                    'salary'         => $salary,
                    'shift'          => $shiftLeave,
                    'day_off'        => $dayOffStatus,
                    'leave'          => $leave,
                    'is_weekend_day' => $isWeekend
                ];
            }

            $data[] = [
                'month'    => $month,
                'year'     => $year,
                'user'     => [
                    'id'         => $user->id,
                    'idkey'      => $user->idkey,
                    'fullname'   => $user->fullname,
                    'department' => $user->departments->pluck('name')
                ],
                'days'     => $workingDays,
                'total'    => $totalWorkingDays
            ];
        }
        return response()->json([
            'code'    => OK,
            'message' => '',
            'data'    => $data
        ], SUCCESS);
    }

    private function getDaysOfMonth($month, $year)
    {
        $previousMonth = $month - 1;
        $previousYear = $year;

        if ($previousMonth === 0) {
            $previousMonth = 12;
            $previousYear = $year - 1;
        }

        // Lấy số ngày trong tháng trước
        $daysInPreviousMonth = cal_days_in_month(CAL_GREGORIAN, $previousMonth, $previousYear);

        // Lấy ngày từ 16 đến cuối tháng trước
        $previousMonthDays = range(16, $daysInPreviousMonth);

        // Lấy ngày từ 1 đến 15 của tháng hiện tại
        $currentMonthDays = range(1, 15);

        $dates = [];

        // Thêm các ngày từ tháng trước vào mảng
        foreach ($previousMonthDays as $day) {
            $dates[] = sprintf('%04d-%02d-%02d', $previousYear, $previousMonth, $day);
        }

        // Thêm các ngày từ tháng hiện tại vào mảng
        foreach ($currentMonthDays as $day) {
            $dates[] = sprintf('%04d-%02d-%02d', $year, $month, $day);
        }

        return $dates;
    }

    public function checkReport(Request $request)
    {
        if ($request->filled('month')) {
            $month = $request->input('month');
        } else {
            $month = Carbon::now()->month;
        }

        if ($request->filled('year')) {
            $year = $request->input('year');
        } else {
            $year = Carbon::now()->year;
        }

        $data = [];
        $validator = Validator::make($request->all(), [
            'items'      => 'array',
            'items.*.id' => 'integer|exists:m_users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $totalDays = $this->getDaysOfMonth($month, $year);
        $startDate = reset($totalDays);
        $endDate = end($totalDays);


        $userIds = collect($request->input('items'))->pluck('id');
        $users = User::query();
        $users = $users->whereIn('id', $userIds);
        $users = $users->whereHas('leaves', function ($query) use ($startDate, $endDate) {
            $query->selectRaw('count(*) as COUNT_LEAVE')
                ->where(function ($query) {
                    $query->where('t_leaves.status', STATUS_PENDING_APPROVAL)
                        ->orWhere('t_leaves.cancel_request', CANCEL_REQUEST_ARE_REQUESTING);
                })
                ->where('day_leave', '>=', $startDate)
                ->where('day_leave', '<=', $endDate)
                ->groupBy('user_id')
                ->havingRaw('COUNT_LEAVE > 0');
        })->select('id', 'idkey', 'fullname', 'image');

        if ($request->filled('sort_by') || $request->filled('sort_order')) {
            $validSortColumns = ['id', 'idkey', 'employee_name'];

            $sortBy = $request->query('sort_by', 'idkey');
            $sortOrder = $request->query('sort_order', 'asc');

            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'idkey';
            }

            $sortOrder = $sortOrder === 'desc' ? 'desc' : 'asc';

            if ($sortBy === "employee_name") {
                $users = $users->orderByRaw("SUBSTRING_INDEX(fullname, ' ', -1) $sortOrder");
            } else {
                $users = $users->orderByRaw("CAST(SUBSTRING(idkey, 3) AS UNSIGNED) $sortOrder");
            }
        } else {
            $users = $users->orderByRaw("CAST(SUBSTRING(idkey, 3) AS UNSIGNED) ASC");
        }

        $users = $users->get();

        $data[] = [
            'start_date'     => Carbon::parse($startDate)->format('d/m/Y'),
            'end_date'       => Carbon::parse($endDate)->format('d/m/Y'),
            'users'          => $users
        ];
        return response()->json([
            'code'    => OK,
            'message' => '',
            'data'    => $data,
            'total'   => $users->count()
        ], SUCCESS);
    }
}
