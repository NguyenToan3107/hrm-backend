<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeaveUserResource;
use App\Models\DayOff;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class LeaveController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:leave_list')->only('index', 'show');
        $this->middleware('permission:leave_create')->only('store', 'update', 'cancelRequest');
        $this->middleware('permission:leave_execute')->only('confirm', 'cancel', 'skipCancelRequest');
        $this->middleware('permission:add_supplementary')->only('adminCreateLeave');
    }

    /**
     * Get all leaves
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'create_date'        => 'sometimes|date_format:d/m/Y',
            'leave_start_date'   => 'sometimes|date_format:d/m/Y',
            'leave_end_date'     => 'sometimes|date_format:d/m/Y',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors(),
            ], CLIENT_ERROR);
        }

        $user = auth()->user();
        $leaves = Leave::where('is_delete', DELETED_N);
        // if ($request->filled('related_leave')) {
        //     $relatedLeave = filter_var($request->query('related_leave'), FILTER_VALIDATE_BOOLEAN);
        //     if ($relatedLeave) {
        //         $leaves->where(function ($query) use ($user) {
        //             $query->where('approver_id', $user->id)
        //                 ->orWhere('user_id', $user->id);
        //         });
        //     }
        // }
        // else {
        //     $leaves->where(function ($query) use ($user) {
        //         $query->where('approver_id', $user->id)
        //             ->orWhere('user_id', $user->id);
        //     });
        // }

        // Search: employee_name, leave_type, status, create_date, leave_date, cancel_request, idkey, approver
        if ($request->filled('leave_type')) {
            $leaveType = explode(",", $request->query('leave_type'));
            $leaves->whereIn('salary', $leaveType);
        }

        if ($request->filled('leave_id')) {
            $leaveId = $request->query('leave_id');
            $leaves->where('idkey', 'LIKE', "%{$leaveId}%");
        }

        if ($request->filled('employee_name')) {
            $fullName = $request->query('employee_name');
            $leaves = $leaves->whereHas('user', function ($query) use ($fullName) {
                $query->where('fullname', 'LIKE', "%{$fullName}%")
                    ->orWhere('idkey', 'LIKE', "%{$fullName}%");
            });
        }

        if ($request->filled('approver')) {
            $approver = $request->query('approver');
            $leaves = $leaves->whereHas('userApprove', function ($query) use ($approver) {
                $query->where('fullname', 'LIKE', "%{$approver}%")
                    ->orWhere('idkey', 'LIKE', "%{$approver}%");
            });
        }

        if ($request->filled('status')) {
            $status = explode(",", $request->query('status'));
            $leaves->whereIn('status', $status);
        }

        if ($request->filled('create_date')) {
            $createDate = \DateTime::createFromFormat('d/m/Y', $request->query('create_date'));
            $formattedCreateDate = $createDate->format('Y-m-d');
            $leaves = $leaves->whereDate('created_at', $formattedCreateDate);
        }

        if ($request->filled('leave_start_date')) {
            $startDate = \DateTime::createFromFormat('d/m/Y', $request->input('leave_start_date'))->format('Y-m-d');
            $leaves = $leaves->where('day_leave', '>=', $startDate);
        }

        if ($request->filled('leave_end_date')) {
            $endDate = \DateTime::createFromFormat('d/m/Y', $request->input('leave_end_date'))->format('Y-m-d');
            $leaves = $leaves->where('day_leave', '<=', $endDate);
        }

        if ($request->filled('cancel_request')) {
            $status = explode(",", $request->query('cancel_request'));
            $leaves->whereIn('cancel_request', $status);
        }

        // Sort
        if ($request->filled('sort_by') || $request->filled('sort_order')) {
            $validSortColumns = ['created_at', 'status', 'salary', 'description', 'fullname', 'day_leaves', 'idkey'];

            $sortBy = $request->query('sort_by', 'created_at');
            $sortOrder = $request->query('sort_order', 'asc');

            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'created_at';
            }

            $sortOrder = $sortOrder === 'desc' ? 'desc' : 'asc';

            if ($sortBy == "fullname") {
                $leaves = $leaves->whereHas('user')
                    ->join('m_users', 't_leaves.user_id', '=', 'm_users.id')
                    ->select('t_leaves.*')
                    ->orderByRaw("
                        SUBSTRING_INDEX(m_users.fullname, ' ', -1) $sortOrder,  -- Tên
                        CASE
                            WHEN LENGTH(m_users.fullname) - LENGTH(REPLACE(m_users.fullname, ' ', '')) >= 1 THEN
                                SUBSTRING_INDEX(SUBSTRING_INDEX(m_users.fullname, ' ', -2), ' ', 1)  -- Tên đệm (chỉ lấy từ đứng trước tên)
                            ELSE ''  -- Không có tên đệm
                        END $sortOrder,
                        SUBSTRING_INDEX(m_users.fullname, ' ', 1) $sortOrder  -- Họ
                    ");
            } elseif ($sortBy == "salary") {
                $leaves->orderByRaw("salary $sortOrder");
            } elseif ($sortBy === "description") {
                $leaves = $leaves->orderByRaw("LENGTH(description) $sortOrder");
            } elseif ($sortBy === "day_leaves") {
                $leaves = $leaves->orderByRaw("day_leave $sortOrder");
            } elseif ($sortBy === "idkey") {
                $leaves = $leaves->orderByRaw("CAST(SUBSTRING(idkey, 2) AS UNSIGNED) $sortOrder");
            } else {
                $leaves = $leaves->orderBy($sortBy, $sortOrder);
            }
        } else {
            $leaves = $leaves->orderByRaw('CASE
                    WHEN status = 0 AND cancel_request = 1 THEN 0
                    WHEN status = 0 AND cancel_request <> 1 THEN 1
                    WHEN status = 1 AND cancel_request = 1 THEN 2
                    WHEN status = 1 AND cancel_request <> 1 THEN 3
                ELSE 4 END')
                ->orderByRaw('DATE(created_at) desc')
                ->orderBy('cancel_request', 'asc')
                ->orderBy('day_leave', 'asc');
        }

        // Pagination
        $limit = $request->filled('limit') ? $request->get('limit') : DEFAULT_PAGE_SIZE;
        if ($limit > MAX_DEFAULT_PAGE_SIZE) {
            $limit = MAX_DEFAULT_PAGE_SIZE;
        }
        $leaves = $leaves->paginate($limit);

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => LeaveUserResource::collection($leaves),
            'total'   => $leaves->total(),
        ], SUCCESS);
    }

    /**
     * Get leave detail
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:t_leaves,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'ID không hợp lệ hoặc không tồn tại',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }
        $id = $request->input('id');
        $leave = Leave::where('is_delete', DELETED_N)
            ->where('id', $id)
            ->first();

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => new LeaveUserResource($leave),
        ], SUCCESS);
    }

    /**
     * Create leave
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     **/
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'day_leave'        => 'required|date_format:d/m/Y|after_or_equal:today',
            'shift'            => 'required',
            'description'      => 'required|string',
            'approver_id'      => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $user = auth()->user();
        $shiftChoseUser = $request->input('shift');
        $formattedDayLeave = \DateTime::createFromFormat('d/m/Y', $request->input('day_leave'))->format('Y-m-d');

        $leaves = Leave::where('user_id', $user->id)
            ->whereDate('day_leave', $formattedDayLeave)
            ->where('is_delete', DELETED_N)
            ->where('status', '<>', STATUS_REJECT)
            ->get();

        $dayOff = DayOff::select('day_off', 'status')
            // ->where('country', $user->country)
            ->where('is_delete', DELETED_N)
            ->where('day_off', $formattedDayLeave)
            ->first();

        if ($dayOff) {
            if ($dayOff->status == STATUS_DAY_OFF_DAY) {
                return errorResponse(NO_CREATE_LEAVE_ON_DAY_OFF);
            }
        } else {
            $dayLeaveCarbon = Carbon::createFromFormat('d/m/Y', $request->input('day_leave'));
            if ($dayLeaveCarbon->isSaturday() || $dayLeaveCarbon->isSunday()) {
                return errorResponse(DAY_IS_WEEKEND);
            }
        }

        // Check exist leave in db
        if ($leaves->count() > 0) {
            foreach ($leaves as $leave) {
                // The leave is pending approval during the day => No additional creation is allowed
                if ($leave->status == STATUS_PENDING_APPROVAL && $leave->day_leave == $formattedDayLeave) {
                    return errorResponse(EXISTED_LEAVE_WAITING_ON_DAY);
                }
                // The leave approved during the day
                if ($leave->status == STATUS_APPROVAL && $leave->day_leave == $formattedDayLeave) {
                    // The leave is all day => No additional creation is allowed
                    if ($leave->shift == SHIFT_ALL_DAY) {
                        return errorResponse(SHIFT_OF_LEAVE_EXISTED_ON_DAY);
                    }
                    // Reject if the selected shift matches the previous shift
                    if ($shiftChoseUser == $leave->shift) {
                        if ($shiftChoseUser == SHIFT_MORNING) {
                            return errorResponse(SHIFT_OF_LEAVE_EXISTED_MORNING);
                        }
                        if ($shiftChoseUser == SHIFT_AFTERNOON) {
                            return errorResponse(SHIFT_OF_LEAVE_EXISTED_AFTERNOON);
                        }
                    }
                }
            }
        }

        if ($user->hasRole('admin')) {
            $leaveFirst = Leave::where('user_id', $user->id)
                ->whereDate('day_leave', $formattedDayLeave)
                ->where('status', STATUS_APPROVAL)
                ->where('is_delete', DELETED_N)
                ->first();
        }

        DB::beginTransaction();

        $mergeLeave = 'Thành công';
        $leaveFirstIdkey = '';
        $leaveSecondIdkey = '';
        try {
            $lastIdKey = Leave::query()
                ->selectRaw('CAST(SUBSTRING(idkey, 2) AS UNSIGNED) AS idNum')
                ->orderByDesc('idNum')
                ->first();
            if ($lastIdKey) {
                $nextId = $lastIdKey->idNum + 1;
            } else {
                $nextId = 1;
            }
            // L00001
            $newIdKey = 'L' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

            $leave = Leave::create([
                'user_id'           => $user->id,
                'shift'             => $shiftChoseUser,
                'status'            => $user->hasRole('admin') ? STATUS_APPROVAL : STATUS_PENDING_APPROVAL,
                'day_leave'         => $formattedDayLeave,
                'description'       => $request->input('description'),
                'other_info'        => $request->input('other_info') ?? null,
                'cancel_request'    => LEAVE_NO_CANCEL_REQUEST,
                'approver_id'       => $request->input('approver_id'),
                'idkey'             => $newIdKey,
            ]);

            if ($user->hasRole('admin')) {
                $totalTimeOff = $user->time_off_hours + $user->last_year_time_off;
                if (!$leaveFirst) {
                    // total time include last year and current year time off
                    $leaveTime = $leave->shift == SHIFT_ALL_DAY ? TIME_ALL_DAY : TIME_HALF_DAY;
                    // calculate salary
                    $salary = UNPAID_LEAVE;
                    $timeSourceLeave = calculateTimeSource($user, $leaveTime);
                    if ($user->status_working == STATUS_OFFICIAL) {
                        if ($totalTimeOff >= $leaveTime) {
                            $salary = PAID_LEAVE;
                            // calculate time off
                            calculateTimeOff($leaveTime, $user);
                        } else {
                            $salary = UNPAID_LEAVE;
                        }
                    }
                    $leave->update([
                        'salary'            => $salary,
                        'approval_date'     => now()->format('Y-m-d'),
                        'approver_id'       => auth()->user()->id,
                        'time_source'       => $timeSourceLeave
                    ]);
                }
                // merge leave admin
                if ($leaveFirst) {
                    // calculator salary
                    $leaveSecondTime = TIME_HALF_DAY;
                    $salaryLeaveFirst = $leaveFirst->salary;

                    // calculate time source
                    $timeSourceLeave = calculateTimeSource($user, $leaveSecondTime);

                    // TH: là nhân viên chính thức
                    if ($user->status_working == STATUS_OFFICIAL) {
                        // TH: đơn 1 nghỉ ko lương
                        if ($salaryLeaveFirst == UNPAID_LEAVE) {
                            // TH1: giờ phép = 4
                            if ($totalTimeOff == TIME_HALF_DAY) {
                                $updateSalaryLeaveFirst = UNPAID_LEAVE;
                            }
                            // TH2: giờ phép > 4
                            if ($totalTimeOff > TIME_HALF_DAY) {
                                $updateSalaryLeaveFirst = PAID_LEAVE;
                                calculateTimeOff(TIME_ALL_DAY, $user);
                            } else {
                                $updateSalaryLeaveFirst = UNPAID_LEAVE;
                            }
                            // TH: đơn 1 nghỉ có lương
                        } else {
                            // TH1: giờ phép >= 4
                            if ($totalTimeOff >= TIME_HALF_DAY) {
                                $updateSalaryLeaveFirst = PAID_LEAVE;
                                calculateTimeOff(TIME_HALF_DAY, $user);
                                // TH2: giờ phép < 4
                            } else {
                                $updateSalaryLeaveFirst = UNPAID_LEAVE;
                            }
                        }
                        // TH: ko là nhân viên chính thức
                    } else {
                        $updateSalaryLeaveFirst = UNPAID_LEAVE;
                    }

                    // if leave first paid and second leave unpaid, refund 4h for admin
                    if ($totalTimeOff == 0 && $salaryLeaveFirst == PAID_LEAVE) {
                        if ($leaveFirst->time_source == CURRENT_YEAR_TIME_OFF) {
                            $user->time_off_hours = $user->time_off_hours + TIME_HALF_DAY;
                        } elseif ($leaveFirst->time_source == LAST_YEAR_TIME_OFF) {
                            $user->last_year_time_off = $user->last_year_time_off + TIME_HALF_DAY;
                        }
                        $user->save();
                    }
                    // if time off > time shift and leave first paid but user not offical => refund 4h
                    if ($totalTimeOff >= TIME_HALF_DAY && $salaryLeaveFirst == PAID_LEAVE) {
                        if ($user->status_working != STATUS_OFFICIAL) {
                            if ($leaveFirst->time_source == CURRENT_YEAR_TIME_OFF) {
                                $user->time_off_hours = $user->time_off_hours + TIME_HALF_DAY;
                            } else if ($leaveFirst->time_source == LAST_YEAR_TIME_OFF) {
                                $user->last_year_time_off = $user->last_year_time_off + TIME_HALF_DAY;
                            }
                            $user->save();
                        }
                    }

                    if ($timeSourceLeave != $leaveFirst->time_source) {
                        $leaveFirst->time_source = BOTH_TIME_OFF;
                    }
                    if ($leaveFirst->cancel_request == CANCEL_REQUEST_ARE_REQUESTING || $leave->cancel_request == CANCEL_REQUEST_ARE_REQUESTING) {
                        $leaveFirst->update([
                            'cancel_request'  => CANCEL_REQUEST_CANCEL,
                        ]);
                    }
                    $leaveFirst->update([
                        'time_source'    => $leaveFirst->time_source,
                        'status'         => STATUS_APPROVAL,
                        'salary'         => $updateSalaryLeaveFirst,
                        'shift'          => SHIFT_ALL_DAY,
                        'approval_date'  => now()->format('Y-m-d'),
                        'approver_id'    => auth()->user()->id
                    ]);
                    // message merge leave
                    $mergeLeave = 'LEAVE_IS_MERGED';
                    $leaveFirstIdkey = $leaveFirst->idkey;
                    $leaveSecondIdkey = $leave->idkey;
                    // delete leave second
                    $leave->delete();
                }
            }

            DB::commit();

            return response()->json([
                'code'             => OK,
                'message'          => $mergeLeave ? $mergeLeave : 'Thành công',
                'data'             => [
                    'leaveFirstIdkey'  => $leaveFirstIdkey,
                    'leaveSecondIdkey' => $leaveSecondIdkey,
                    new LeaveUserResource($leave)
                ]
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'code'    => ERROR,
                'message' => 'Thất bại',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    /**
     * Update leave
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     **/
    public function update(Request $request)
    {
        $id = $request->input('id');

        $validator = Validator::make($request->all(), [
            'id'               => 'required|integer|exists:t_leaves,id',
            'day_leave'        => 'nullable|date_format:d/m/Y|after_or_equal:today',
            'shift'            => 'nullable',
            'description'      => 'nullable|string',
            'approver_id'      => 'nullable|integer|exists:m_users,id',
            'updated_at'       => 'required|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $leave = Leave::where('is_delete', DELETED_N)
            ->where('id', $id)
            ->first();
        $user = $leave->user;
        $userLogin = auth()->user();

        if ($user->id !== $userLogin->id) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Chỉ người tạo đơn mới có thể sửa đơn',
                'data'    => []
            ], CLIENT_ERROR);
        }

        if ($leave->status == STATUS_REJECT) {
            return errorResponse(LEAVE_REJECTED);
        }

        $formattedDayLeave = $request->input('day_leave')
            ? \DateTime::createFromFormat('d/m/Y', $request->input('day_leave'))->format('Y-m-d')
            : $leave->day_leave;

        // The leave is not pending approval during the day => No additional creation is allowed
        if ($leave->status != STATUS_PENDING_APPROVAL) {
            return errorResponse(NO_ADDITIONAL_LEAVE_ALLOWED);
        }
        $formattedDayCheck = \DateTime::createFromFormat('d/m/Y', $request->input('day_leave'))->format('Y-m-d');

        // hàm kiểm tra edit có vào ngày nghỉ ko
        $dayOff = DayOff::select('day_off', 'status')
            ->where('is_delete', DELETED_N)
            ->where('day_off', $formattedDayCheck)
            ->first();
        if ($dayOff) {
            if ($dayOff->status == STATUS_DAY_OFF_DAY) {
                return errorResponse(NO_CREATE_LEAVE_ON_DAY_OFF);
            }
        } else {
            $dayLeaveCarbon = Carbon::createFromFormat('d/m/Y', $request->input('day_leave'));
            if ($dayLeaveCarbon->isSaturday() || $dayLeaveCarbon->isSunday()) {
                return errorResponse(DAY_IS_WEEKEND);
            }
        }
        $leaves = Leave::where('user_id', $user->id)
            ->whereDate('day_leave', $formattedDayCheck)
            ->where('is_delete', DELETED_N)
            ->where('status', '<>', STATUS_REJECT)
            ->get();
        $shiftChoseUser = $request->input('shift');
        if ($leaves->count() > 0) {
            foreach ($leaves as $leaveChoose) {
                if ($leaveChoose->status == STATUS_APPROVAL) {
                    // Nếu đơn là cả ngày rồi -> ko edit được
                    if ($leaveChoose->shift == SHIFT_ALL_DAY) {
                        return errorResponse(SHIFT_OF_LEAVE_EXISTED_ON_DAY);
                    }
                    // Nếu shift chọn trùng với shift edit
                    if ($shiftChoseUser == $leaveChoose->shift) {
                        if ($shiftChoseUser == SHIFT_MORNING) {
                            return errorResponse(SHIFT_OF_LEAVE_EXISTED_MORNING);
                        }
                        if ($shiftChoseUser == SHIFT_AFTERNOON) {
                            return errorResponse(SHIFT_OF_LEAVE_EXISTED_AFTERNOON);
                        }
                    }
                } else {
                    if ($leave->day_leave != $formattedDayCheck) {
                        return errorResponse(EXISTED_LEAVE_WAITING_ON_DAY);
                    }
                }
            }
        }

        DB::beginTransaction();

        try {
            // check concurrency
            $requestUpdatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $request->input('updated_at'))->getTimestamp();
            $dbUpdatedAt = $leave->updated_at->getTimestamp();
            if ($dbUpdatedAt !== $requestUpdatedAt) {
                DB::rollback();

                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu đã được cập nhật bởi người khác. Vui lòng tải lại trang và thử lại.',
                    'data'    => []
                ], CLIENT_ERROR);
            }

            $leave->update([
                'user_id'           => $user->id,
                'shift'             => $request->input('shift') ?? $leave->shift,
                'status'            => STATUS_PENDING_APPROVAL,
                'day_leave'         => $formattedDayLeave,
                'description'       => $request->input('description') ?? $leave->description,
                'other_info'        => $request->input('other_info') ?? $leave->other_info,
                //                'cancel_request'    => LEAVE_NO_CANCEL_REQUEST,
                'approver_id'       => $request->input('approver_id') ?? $leave->approver_id,
            ]);

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Thành công',
                'data'    => new LeaveUserResource($leave)
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Thất bại',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    /**
     * Send cancel request leave
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     **/
    public function cancelRequest(Request $request)
    {
        $id = $request->input('id');

        $validator = Validator::make($request->all(), [
            'id'               => 'required|integer|exists:t_leaves,id',
            'updated_at'       => 'required|date_format:Y-m-d H:i:s',
            'description'      => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $leave = Leave::where('is_delete', DELETED_N)
            ->where('id', $id)
            ->first();
        $user = $leave->user;
        $userLogin = auth()->user();

        if ($user->id !== $userLogin->id) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Chỉ người tạo đơn mới có thể gửi can request',
                'data'    => [
                    'CREATOR_ONLY_PERMISSION' => [
                        CREATOR_ONLY_PERMISSION
                    ]
                ]
            ], CLIENT_ERROR);
        }

        if ($leave->cancel_request != CANCEL_REQUEST_NO) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Không thể gửi cancel request',
                'data'    => [
                    'CANCEL_REQUEST_NOT_SEND' => [
                        CANCEL_REQUEST_NOT_SEND
                    ]
                ]
            ], CLIENT_ERROR);
        }

        if ($leave->status == STATUS_REJECT) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Đơn này đã hủy',
                'data'    => [
                    'LEAVE_REJECTED' => [
                        LEAVE_REJECTED
                    ]
                ]
            ], CLIENT_ERROR);
        }

        DB::beginTransaction();

        try {
            // check concurrency
            $requestUpdatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $request->input('updated_at'))->getTimestamp();
            $dbUpdatedAt = $leave->updated_at->getTimestamp();

            if ($dbUpdatedAt !== $requestUpdatedAt) {
                DB::rollback();

                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu đã được cập nhật bởi người khác. Vui lòng tải lại trang và thử lại.',
                    'data'    => []
                ], CLIENT_ERROR);
            }

            $leave->update([
                'cancel_request'         => CANCEL_REQUEST_ARE_REQUESTING,
                'cancel_request_desc'    => $request->input('description'),
            ]);

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Thành công',
                'data'    => new LeaveUserResource($leave)
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Thất bại',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    /**
     * Skip can request leave (admin)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     **/
    public function skipCancelRequest(Request $request)
    {
        $id = $request->input('id');

        $validator = Validator::make($request->all(), [
            'id'               => 'required|integer|exists:t_leaves,id',
            'updated_at'       => 'required|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $leave = Leave::where('is_delete', DELETED_N)
            ->where('id', $id)
            ->first();

        if ($leave->status == STATUS_REJECT) {
            return response()->json([
                'code'    => OUT_DATE,
                'message' => 'Đơn này đã hủy',
                'data'    => []
            ], CLIENT_ERROR);
        }

        DB::beginTransaction();

        try {
            // check concurrency
            $requestUpdatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $request->input('updated_at'))->getTimestamp();
            $dbUpdatedAt = $leave->updated_at->getTimestamp();

            if ($dbUpdatedAt !== $requestUpdatedAt) {
                DB::rollback();

                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu đã được cập nhật bởi người khác. Vui lòng tải lại trang và thử lại.',
                    'data'    => []
                ], CLIENT_ERROR);
            }

            $leave->update([
                'cancel_request'   => CANCEL_REQUEST_CANCEL,
                'approver_id'      => auth()->user()->id
            ]);

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Thành công',
                'data'    => new LeaveUserResource($leave)
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Thất bại',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    /**
     * Confirm leave (admin)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     **/
    public function confirm(Request $request)
    {
        $id = $request->input('id');

        $validator = Validator::make($request->all(), [
            'id'               => 'required|integer|exists:t_leaves,id',
            'updated_at'       => 'required|date_format:Y-m-d H:i:s',
        ]);

        $userLogin = JWTAuth::parseToken()->authenticate();

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $leave = Leave::where('is_delete', DELETED_N)
            ->where('status', '<>', STATUS_REJECT)
            ->where('id', $id)
            ->first();
        $user = $leave->user;
        $leaveFirst = Leave::where('user_id', $user->id)
            ->whereDate('day_leave', $leave->day_leave)
            ->where('status', STATUS_APPROVAL)
            ->where('is_delete', DELETED_N)
            ->first();

        // total time include last year and current year time off
        $totalTimeOff = $user->time_off_hours + $user->last_year_time_off;

        DB::beginTransaction();
        try {
            // check concurrency
            $requestUpdatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $request->input('updated_at'))->getTimestamp();
            $dbUpdatedAt = $leave->updated_at->getTimestamp();

            if ($dbUpdatedAt !== $requestUpdatedAt) {
                DB::rollback();

                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu đã được cập nhật bởi người khác. Vui lòng tải lại trang và thử lại.',
                    'data'    => []
                ], CLIENT_ERROR);
            }

            $mergeLeave = '';
            $leaveFirstIdkey = '';
            $leaveSecondIdkey = '';
            // no leave in day
            if (!$leaveFirst) {
                $leaveTime = $leave->shift == SHIFT_ALL_DAY ? TIME_ALL_DAY : TIME_HALF_DAY;
                // calculate salary
                $salary = UNPAID_LEAVE;
                $timeSourceLeave = calculateTimeSource($user, $leaveTime);
                if ($user->status_working == STATUS_OFFICIAL) {
                    if ($totalTimeOff >= $leaveTime) {
                        $salary = PAID_LEAVE;
                        // calculate time off
                        calculateTimeOff($leaveTime, $user);
                    } else {
                        $salary = UNPAID_LEAVE;
                    }
                }

                if ($leave->cancel_request == CANCEL_REQUEST_ARE_REQUESTING) {
                    $leave->update([
                        'cancel_request'  => CANCEL_REQUEST_CANCEL,
                    ]);
                }
                $leave->update([
                    'status'            => STATUS_APPROVAL,
                    'salary'            => $salary,
                    'approval_date'     => now()->format('Y-m-d'),
                    'approver_id'       => $userLogin->id,
                    'time_source'       => $timeSourceLeave
                ]);
            } else {
                // exist leave in day
                $leaveTime = TIME_HALF_DAY;
                $salaryLeaveFirst = $leaveFirst->salary;

                // calculate time source
                $timeSourceLeave = calculateTimeSource($user, $leaveTime);

                // calculate salary
                // TH: là nhân viên chính thức
                if ($user->status_working == STATUS_OFFICIAL) {
                    // TH: đơn 1 nghỉ ko lương
                    if ($salaryLeaveFirst == UNPAID_LEAVE) {
                        // TH1: giờ phép = 4
                        if ($totalTimeOff == TIME_HALF_DAY) {
                            $updateSalaryLeaveFirst = UNPAID_LEAVE;
                        }
                        // TH2: giờ phép > 4
                        if ($totalTimeOff > TIME_HALF_DAY) {
                            $updateSalaryLeaveFirst = PAID_LEAVE;
                            calculateTimeOff(TIME_ALL_DAY, $user);
                        } else {
                            $updateSalaryLeaveFirst = UNPAID_LEAVE;
                        }
                        // TH: đơn 1 nghỉ có lương
                    } else {
                        // TH1: giờ phép >= 4
                        if ($totalTimeOff >= TIME_HALF_DAY) {
                            $updateSalaryLeaveFirst = PAID_LEAVE;
                            calculateTimeOff($leaveTime, $user);
                            // TH2: giờ phép < 4
                        } else {
                            $updateSalaryLeaveFirst = UNPAID_LEAVE;
                        }
                    }
                    // TH: ko là nhân viên chính thức
                } else {
                    $updateSalaryLeaveFirst = UNPAID_LEAVE;
                }

                // if leave first paid and second leave unpaid, refund 4h for user
                if ($totalTimeOff == 0 && $salaryLeaveFirst == PAID_LEAVE) {
                    if ($leaveFirst->time_source == CURRENT_YEAR_TIME_OFF) {
                        $user->time_off_hours = $user->time_off_hours + TIME_HALF_DAY;
                    } else if ($leaveFirst->time_source == LAST_YEAR_TIME_OFF) {
                        $user->last_year_time_off = $user->last_year_time_off + TIME_HALF_DAY;
                    }
                    $user->save();
                }
                if ($totalTimeOff >= TIME_HALF_DAY && $salaryLeaveFirst == PAID_LEAVE) {
                    if ($user->status_working != STATUS_OFFICIAL) {
                        if ($leaveFirst->time_source == CURRENT_YEAR_TIME_OFF) {
                            $user->time_off_hours = $user->time_off_hours + TIME_HALF_DAY;
                        } else if ($leaveFirst->time_source == LAST_YEAR_TIME_OFF) {
                            $user->last_year_time_off = $user->last_year_time_off + TIME_HALF_DAY;
                        }
                        $user->save();
                    }
                }

                if ($timeSourceLeave != $leaveFirst->time_source) {
                    $leaveFirst->time_source = BOTH_TIME_OFF;
                }

                if ($leaveFirst->cancel_request == CANCEL_REQUEST_ARE_REQUESTING || $leave->cancel_request == CANCEL_REQUEST_ARE_REQUESTING) {
                    $leaveFirst->update([
                        'cancel_request'  => CANCEL_REQUEST_CANCEL,
                    ]);
                }
                $leaveFirst->update([
                    'time_source'    => $leaveFirst->time_source,
                    'status'         => STATUS_APPROVAL,
                    'salary'         => $updateSalaryLeaveFirst,
                    'shift'          => SHIFT_ALL_DAY,
                    'approval_date'  => now()->format('Y-m-d'),
                    'approver_id'    => $userLogin->id
                ]);
                // message merge leave
                // $mergeLeave = 'LEAVE_IS_MERGED - ' . $leaveFirst->idkey . ' - ' . $leave->idkey;
                $mergeLeave = 'LEAVE_IS_MERGED';
                $leaveFirstIdkey = $leaveFirst->idkey;
                $leaveSecondIdkey = $leave->idkey;
                // delete leave second
                $leave->delete();
            }

            DB::commit();
            return response()->json([
                'code'    => OK,
                'message' => $mergeLeave,
                'data'    => [
                    'leaveFirstIdkey'  => $leaveFirstIdkey,
                    'leaveSecondIdkey' => $leaveSecondIdkey,
                    new LeaveUserResource($leave)
                ]
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Thất bại',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    /**
     * Cancel leave (admin)
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     **/
    public function cancel(Request $request)
    {
        $id = $request->input('id');
        $checkCR = false;

        $validator = Validator::make($request->all(), [
            'id'               => 'required|integer|exists:t_leaves,id',
            'updated_at'       => 'required|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $leave = Leave::where('is_delete', DELETED_N)
            ->where('status', '<>', STATUS_REJECT)
            ->where('id', $id)
            ->first();
        $user = $leave->user;

        DB::beginTransaction();

        try {
            // check concurrency
            $requestUpdatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $request->input('updated_at'))->getTimestamp();
            $dbUpdatedAt = $leave->updated_at->getTimestamp();

            if ($dbUpdatedAt !== $requestUpdatedAt) {
                DB::rollback();

                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu đã được cập nhật bởi người khác. Vui lòng tải lại trang và thử lại.',
                    'data'    => []
                ], CLIENT_ERROR);
            }

            // leave is pending approval
            if ($leave->status == STATUS_PENDING_APPROVAL) {
                // leave had not can request
                if ($leave->cancel_request == CANCEL_REQUEST_NO) {
                    $leave->update([
                        'status'          => STATUS_REJECT,
                        'approval_date'   => now()->format('Y-m-d'),
                        'approver_id'     => auth()->user()->id
                    ]);
                }
                // leave had can request
                if ($leave->cancel_request == CANCEL_REQUEST_ARE_REQUESTING) {
                    $checkCR = true;
                    $leave->update([
                        'status'          => STATUS_REJECT,
                        'cancel_request'  => CANCEL_REQUEST_CONFIRMED,
                        'approval_date'   => now()->format('Y-m-d'),
                        'approver_id'     => auth()->user()->id
                    ]);
                }
                // leave after skip cancel request
                if ($leave->cancel_request == CANCEL_REQUEST_CANCEL) {
                    $leave->update([
                        'status'          => STATUS_REJECT,
                        'approval_date'   => now()->format('Y-m-d'),
                        'approver_id'     => auth()->user()->id
                    ]);
                }
            }

            // leave approved and had can request
            if ($leave->status == STATUS_APPROVAL && $leave->cancel_request == CANCEL_REQUEST_ARE_REQUESTING) {
                $timeOff = ($leave->shift == SHIFT_ALL_DAY) ? TIME_ALL_DAY : TIME_HALF_DAY;

                // calculate time off
                if ($leave->salary != UNPAID_LEAVE) {
                    $timeOff = $leave->salary == PAID_LEAVE ? $timeOff : TIME_HALF_DAY;
                    if ($leave->time_source == LAST_YEAR_TIME_OFF) {
                        $user->update([
                            'last_year_time_off' => $user->last_year_time_off + $timeOff,
                        ]);
                    } elseif ($leave->time_source == CURRENT_YEAR_TIME_OFF) {
                        $user->update([
                            'time_off_hours' => $user->time_off_hours + $timeOff,
                        ]);
                    } elseif ($leave->time_source == BOTH_TIME_OFF) {
                        $user->update([
                            'time_off_hours'     => $user->time_off_hours + TIME_HALF_DAY,
                            'last_year_time_off' => $user->last_year_time_off + TIME_HALF_DAY,
                        ]);
                    }
                }
                $checkCR = true;
                $leave->update([
                    'status'         => STATUS_REJECT,
                    'cancel_request' => CANCEL_REQUEST_CONFIRMED,
                    'approval_date'  => now()->format('Y-m-d'),
                    'salary'         => null,
                    'approver_id'    => auth()->user()->id
                ]);
            }

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Thành công',
                'data'    => new LeaveUserResource($leave)
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Thất bại',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    /**
     * Create leave by admin
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|void
     **/
    public function adminCreateLeave(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'day_leave'        => 'required|date_format:d/m/Y',
            'shift'            => 'required',
            'description'      => 'required|string',
            'user_id'          => 'required|exists:m_users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $admin = auth()->user();

        $userId = $request->input('user_id');
        $user = User::find($userId);
        $shiftChoseAdmin = $request->input('shift');
        $dayLeave = $request->input('day_leave');
        $formattedDayLeave = \DateTime::createFromFormat('d/m/Y', $dayLeave)->format('Y-m-d');

        $leaveFirst = Leave::where('user_id', $user->id)
            ->whereDate('day_leave', $formattedDayLeave)
            ->where('status', STATUS_APPROVAL)
            ->where('is_delete', DELETED_N)
            ->first();

        $leavesWaiting = Leave::where('user_id', $user->id)
            ->whereDate('day_leave', $formattedDayLeave)
            ->where('is_delete', DELETED_N)
            ->where('status', STATUS_PENDING_APPROVAL)
            ->get();


        if ($leavesWaiting->count() > 0) {
            foreach ($leavesWaiting as $leave) {
                // The leave is pending approval during the day => No additional creation is allowed
                return errorResponse(EXISTED_LEAVE_WAITING_ON_DAY);
            }
        }

        $dayOff = DayOff::select('day_off', 'status')
            // ->where('country', $user->country)
            ->where('is_delete', DELETED_N)
            ->where('day_off', $formattedDayLeave)
            ->first();

        if ($dayOff) {
            if ($dayOff->status == STATUS_DAY_OFF_DAY) {
                return errorResponse(NO_CREATE_LEAVE_ON_DAY_OFF);
            }
        } else {
            $dayLeaveCarbon = Carbon::createFromFormat('d/m/Y', $request->input('day_leave'));
            if ($dayLeaveCarbon->isSaturday() || $dayLeaveCarbon->isSunday()) {
                return errorResponse(DAY_IS_WEEKEND);
            }
        }

        // The leave approved during the day
        if ($leaveFirst && $leaveFirst->status == STATUS_APPROVAL) {
            // The leave is all day => No additional creation is allowed
            if ($leaveFirst->shift == SHIFT_ALL_DAY) {
                return errorResponse(SHIFT_OF_LEAVE_EXISTED_ON_DAY);
            }
            // Reject if the selected shift matches the previous shift
            if ($shiftChoseAdmin == $leaveFirst->shift) {
                if ($shiftChoseAdmin == SHIFT_MORNING) {
                    return errorResponse(SHIFT_OF_LEAVE_EXISTED_MORNING);
                }
                if ($shiftChoseAdmin == SHIFT_AFTERNOON) {
                    return errorResponse(SHIFT_OF_LEAVE_EXISTED_AFTERNOON);
                }
            }
        }

        DB::beginTransaction();

        try {
            $lastIdKey = Leave::query()
                ->selectRaw('CAST(SUBSTRING(idkey, 2) AS UNSIGNED) AS idNum')
                ->orderByDesc('idNum')
                ->first();
            if ($lastIdKey) {
                $nextId = $lastIdKey->idNum + 1;
            } else {
                $nextId = 1;
            }
            // L00001
            $newIdKey = 'L' . str_pad($nextId, 5, '0', STR_PAD_LEFT);

            // total time off
            $totalTimeOff = $user->time_off_hours + $user->last_year_time_off;

            // no leave in day
            if (!$leaveFirst) {
                $leaveTime = $shiftChoseAdmin == SHIFT_ALL_DAY ? TIME_ALL_DAY : TIME_HALF_DAY;

                // calculator time source
                $timeSourceLeave = calculateTimeSource($user, $leaveTime);

                // calculate salary
                $salary = UNPAID_LEAVE;
                if ($user->status_working == STATUS_OFFICIAL) {
                    if ($totalTimeOff >= $leaveTime) {
                        $salary = PAID_LEAVE;
                        // calculate time off
                        calculateTimeOff($leaveTime, $user);
                    } else {
                        $salary = UNPAID_LEAVE;
                    }
                }

                $leave = Leave::create([
                    'user_id'           => $user->id,
                    'shift'             => $shiftChoseAdmin,
                    'status'            => STATUS_APPROVAL,
                    'day_leave'         => $formattedDayLeave,
                    'description'       => $request->input('description'),
                    'other_info'        => $request->input('other_info') ?? null,
                    'cancel_request'    => LEAVE_NO_CANCEL_REQUEST,
                    'approver_id'       => $admin->id,
                    'salary'            => $salary,
                    'approval_date'     => now()->format('Y-m-d'),
                    'time_source'       => $timeSourceLeave,
                    'idkey'             => $newIdKey,
                ]);

                DB::commit();

                return response()->json([
                    'code'    => OK,
                    'message' => '',
                    'data'    => new LeaveUserResource($leave)
                ], SUCCESS);
            } else {
                // exist leave in day
                $leaveTime = TIME_HALF_DAY;
                $salaryLeaveFirst = $leaveFirst->salary;
                // calculator time source
                $timeSourceLeave = calculateTimeSource($user, $leaveTime);

                // calculate salary
                // TH: là nhân viên chính thức
                if ($user->status_working == STATUS_OFFICIAL) {
                    // TH: đơn 1 nghỉ ko lương
                    if ($salaryLeaveFirst == UNPAID_LEAVE) {
                        // TH1: giờ phép = 4
                        if ($totalTimeOff == TIME_HALF_DAY) {
                            $updateSalaryLeaveFirst = UNPAID_LEAVE;
                        }
                        // TH2: giờ phép > 4
                        if ($totalTimeOff > TIME_HALF_DAY) {
                            $updateSalaryLeaveFirst = PAID_LEAVE;
                            calculateTimeOff(TIME_ALL_DAY, $user);
                        } else {
                            $updateSalaryLeaveFirst = UNPAID_LEAVE;
                        }
                        // TH: đơn 1 nghỉ có lương
                    } else {
                        // TH1: giờ phép >= 4
                        if ($totalTimeOff >= TIME_HALF_DAY) {
                            $updateSalaryLeaveFirst = PAID_LEAVE;
                            calculateTimeOff($leaveTime, $user);
                            // TH2: giờ phép < 4
                        } else {
                            $updateSalaryLeaveFirst = UNPAID_LEAVE;
                        }
                    }
                    // TH: ko là nhân viên chính thức
                } else {
                    $updateSalaryLeaveFirst = UNPAID_LEAVE;
                }

                // if leave first paid and second leave unpaid, refund 4h for user
                if ($totalTimeOff == 0 && $salaryLeaveFirst == PAID_LEAVE) {
                    if ($leaveFirst->time_source == CURRENT_YEAR_TIME_OFF) {
                        $user->time_off_hours = $user->time_off_hours + TIME_HALF_DAY;
                    } else if ($leaveFirst->time_source == LAST_YEAR_TIME_OFF) {
                        $user->last_year_time_off = $user->last_year_time_off + TIME_HALF_DAY;
                    }
                    $user->save();
                }
                if ($totalTimeOff >= TIME_HALF_DAY && $salaryLeaveFirst == PAID_LEAVE) {
                    if ($user->status_working != STATUS_OFFICIAL) {
                        if ($leaveFirst->time_source == CURRENT_YEAR_TIME_OFF) {
                            $user->time_off_hours = $user->time_off_hours + TIME_HALF_DAY;
                        } else if ($leaveFirst->time_source == LAST_YEAR_TIME_OFF) {
                            $user->last_year_time_off = $user->last_year_time_off + TIME_HALF_DAY;
                        }
                        $user->save();
                    }
                }

                if ($timeSourceLeave != $leaveFirst->time_source) {
                    $leaveFirst->time_source = BOTH_TIME_OFF;
                }
                $leaveFirst->update([
                    'time_source'  => $leaveFirst->time_source,
                    'status'       => STATUS_APPROVAL,
                    'salary'       => $updateSalaryLeaveFirst,
                    'shift'        => SHIFT_ALL_DAY,
                ]);

                $mergeLeave = 'LEAVE_IS_MERGED';
                $leaveFirstIdkey = $leaveFirst->idkey;
                DB::commit();

                return response()->json([
                    'code'    => OK,
                    'message' => $mergeLeave ? $mergeLeave : '',
                    'data'    => [
                        'leaveFirstIdkey'  => $leaveFirstIdkey ? $leaveFirstIdkey : '',
                        new LeaveUserResource($leaveFirst)
                    ]
                ], SUCCESS);
            }
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Thất bại',
                'data'    => []
            ], SERVER_ERROR);
        }
    }
}
