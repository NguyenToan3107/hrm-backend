<?php

namespace App\Http\Controllers;

use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\DayOff;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CommonController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:exportPDF')->only('export');
    }

    /**
     * Common
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function index()
    {
        $user = auth()->user();
        $positions = Position::select('id', 'name')
            ->where('is_delete', DELETED_N)
            ->get();

        $departments = Department::select('id', 'name')
            ->where('is_delete', DELETED_N)
            ->get();
        $roles = Role::select('id', 'role_name', 'description')->get();

        // get fullname leader of department
        //        $approveUsers = DB::table('m_department')
        //            ->join('r_user_department', 'm_department.id', '=', 'r_user_department.department_id')
        //            ->join('m_users', 'm_department.leader_id', '=', 'm_users.id')
        //            ->where('r_user_department.user_id', auth()->id())
        //            ->select('m_users.id', 'm_users.fullname')
        //            ->get();

        $approveUsers = User::where('id', $user->leader_id)
            ->select('id', 'idkey', 'fullname')
            ->get();


        return response()->json([
            'code'    => OK,
            'message' => '',
            'data'    => [
                'positions'     => $positions,
                'departments'   => $departments,
                'approve_users' => $approveUsers,
                'roles'         => $roles,

            ]
        ], SUCCESS);
    }

    /**
     * Get list users
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function show()
    {
        $users = User::select('id', 'fullname', 'idkey')
            ->where('status', STATUS_IS_ACTIVE)
            ->orderBy('idkey', 'asc')
            ->get();

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => $users,
            'total'   => count($users),
        ], SUCCESS);
    }

    /**
     * Get list admin and leader
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function showAdminLeader()
    {
        $users = User::select('id', 'idkey', DB::raw('
                    CASE
                        WHEN fullname IS NULL THEN ""
                        ELSE fullname
                    END AS fullname
                '))
            ->where('status', STATUS_IS_ACTIVE)
            ->whereHas('roles.permissions', function ($query) {
                $query->whereIn('name', ['leave_execute']);
            })
            ->orderBy('idkey', 'asc')
            ->get();


        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => $users,
            'total'   => count($users),
        ], SUCCESS);
    }


    /**
     * Get list admin and leader of department
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function showAdminLeaderOfDepartment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids'    => 'array',
            'ids.*'  => 'integer|exists:m_department,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $ids = $request->input('ids');  // department
        $departments = Department::when($request->input('ids'), function ($query, $ids) {
            return $query->whereIn('id', $ids);
        })->get();

        $leaderOfDepartments = $departments->flatMap(function ($department) {
            return $department->users()->select('m_users.id', 'm_users.idkey', DB::raw('
                        CASE WHEN m_users.fullname IS NULL THEN ""
                            ELSE m_users.fullname
                        END AS fullname
                    '))
                ->where('status', STATUS_IS_ACTIVE)
                ->whereHas('roles.permissions', function ($query) {
                    $query->where('name', 'leave_execute');
                })
                ->orderBy('idkey', 'asc')
                ->get();
        })->toArray();

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => $leaderOfDepartments,
            'total'   => count($leaderOfDepartments),
        ], SUCCESS);
    }

    /**
     * Get user by id
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function showDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:m_users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'ID không hợp lệ hoặc không tồn tại',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $user = User::find($request->input('id'));

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => new UserResource($user),
        ], SUCCESS);
    }

    public function listRoles()
    {
        $roles = Role::all();

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => RoleResource::collection($roles),
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
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:m_users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'ID không hợp lệ hoặc không tồn tại',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

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
        $id = $request->input('id');
        $user = User::where('id', $id)
            //            ->where('country', VN)
            ->first();

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
            $dayOff = null;

            if ($leaveRecord) {
                if ($leaveRecord->shift == SHIFT_ALL_DAY) {
                    $isWorkingDay = false;
                } elseif ($leaveRecord->shift == SHIFT_MORNING || $leaveRecord->shift == SHIFT_AFTERNOON) {
                    $isWorkingDay = true;
                    $totalWorkingDays += WORK_HALF_DAY;
                }
                $salary = $leaveRecord->salary;
                $leave = true;
            } elseif ($dayOffRecord) {
                if ($dayOffRecord->status == STATUS_DAY_ON_DAY) {
                    $isWorkingDay = true;
                    $totalWorkingDays++;
                } elseif ($dayOffRecord->status == STATUS_DAY_OFF_DAY) {
                    $isWorkingDay = false;
                    $dayOff = true;
                }
                $salary = PAID_LEAVE;
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

            $workingDays[] = [
                'year'           => $date->year,
                'month'          => $date->month,
                'day'            => $day,
                'day_of_week_jp' => $date->dayOfWeek,
                'is_working_day' => $isWorkingDay,
                'salary'         => $salary,
                'shift'          => $leaveRecord->shift ?? null,
                'day_off'        => $dayOff,
                'leave'          => $leave,
            ];
        }

        return response()->json([
            'code'    => OK,
            'message' => '',
            'data'    => [
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
            ]
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
}
