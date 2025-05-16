<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserCollection;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:staff_master')->except('updateHideNotification');
    }

    /**
     * Get all users
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function index(Request $request)
    {
        $users = User::with('position', 'departments');

        // Search: keyword (ID, fullname, email), status_working, status, email, leave hours, leader name, role
        if ($request->filled('keyword')) {
            $keyword = $request->query('keyword');
            $users->where(function ($query) use ($keyword) {
                $query->where('idkey', 'LIKE', "%{$keyword}%")
                    ->orWhere('fullname', 'LIKE', "%{$keyword}%")
                    ->orWhere('email', 'LIKE', "%{$keyword}%");
            });
        }

        if ($request->filled('role')) {
            $role = $request->query('role');
            if ($role != 'All') {
                $users->role($role);
            }
        }

        if ($request->filled('type')) {
            $statusWorking = explode(",", $request->query('type'));
            $users->whereIn('status_working', $statusWorking);
        }

        if ($request->filled('status')) {
            $status = explode(",", $request->query('status'));
            if (!in_array('-1', $status)) {
                $users->whereIn('m_users.status', $status);
            }
        } else {
            $users->whereIn('m_users.status', [STATUS_IS_ACTIVE]);
        }

        if ($request->filled('position')) {
            $position = $request->query('position');
            $users = $users->whereHas('position', function ($query) use ($position) {
                $query->where('id', $position);
            });
        }

        if ($request->filled('email')) {
            $email = $request->query('email');
            $users->where('email', 'LIKE', "%{$email}%");
        }

        if ($request->filled('leave_hour')) {
            $leaveHour = explode(",", $request->query('leave_hour'));
            $users->whereIn('time_off_hours', $leaveHour);
        }

        if ($request->filled('leader_name')) {
            $leaderName = $request->query('leader_name');
            $users->whereHas('leaderId', function ($query) use ($leaderName) {
                $query->where('fullname', 'LIKE', "%{$leaderName}%");
            });
        }

        // Sort
        if ($request->filled('sort_by') || $request->filled('sort_order')) {
            $validSortColumns = ['id', 'idkey', 'employee_name', 'status', 'status_working', 'role', 'email', 'time_off_hours', 'leader_name', 'role_name'];

            $sortBy = $request->query('sort_by', 'idkey');
            $sortOrder = $request->query('sort_order', 'asc');

            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'idkey';
            }

            $sortOrder = $sortOrder === 'desc' ? 'desc' : 'asc';

            if ($sortBy === "employee_name") {
                $users = $users->orderByRaw("SUBSTRING_INDEX(fullname, ' ', -1) $sortOrder");
            } elseif ($sortBy == "role_name") {
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
     * Get user by id
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function show(Request $request)
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
        $id = $request->input('id');
        $user = User::where('id', $id)
            ->where('status', STATUS_IS_ACTIVE)
            ->where('country', 'vi')
            ->first();

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => new UserResource($user)
        ], SUCCESS);
    }

    /**
     * create new user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'idkey'            => 'required|string|unique:m_users',
            'username'         => 'string|unique:m_users',
            'email'            => 'required|string|email|unique:m_users',
            'status_working'   => 'required',
            'started_at'       => 'required|date_format:d/m/Y',
            'ended_at'         => 'date_format:d/m/Y',
            'position'         => 'integer|exists:m_positions,id',
            'department_ids'   => 'required|array',
            'department_ids.*' => 'integer|exists:m_department,id',
            'time_off_hours'   => 'numeric',
            'leader_id'        => 'required|integer|exists:m_users,id',
            'role'             => 'required',
            'gender'           => 'required',
            //            'fullname'         => 'string',
            //            'birth_day'        => 'date_format:d/m/Y|before:today',
            //            'phone'            => 'regex:/^[0-9]+$/|size:10',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        if ($request->filled('birth_day')) {
            $birthDay = \DateTime::createFromFormat('d/m/Y', $request->input('birth_day'));
            $formattedBirthDay = $birthDay->format('Y-m-d');
        }
        $formattedStartedAt = \DateTime::createFromFormat('d/m/Y', $request->input('started_at'))->format('Y-m-d');

        if ($request->filled('ended_at')) {
            $formattedEndedAt = \DateTime::createFromFormat('d/m/Y', $request->input('ended_at'))->format('Y-m-d');
        }

        DB::beginTransaction();

        try {
            $roleRequest = Role::where('name', $request->input('role'))->first();
            $leaderUserChoose = User::where('id', $request->input('leader_id'))->where('status', STATUS_IS_ACTIVE)->first();
            if (!$roleRequest || !$leaderUserChoose) {
                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu không tồn tại role',
                    'data'    => []
                ], CLIENT_ERROR);
            }
            if (!$leaderUserChoose->hasPermissionTo('leave_execute')) {
                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu không tồn tại role',
                    'data'    => []
                ], CLIENT_ERROR);
            }

            $user = User::create([
                'idkey'          => $request->input('idkey'),
                'fullname'       => $request->input('fullname'),
                'phone'          => $request->input('phone') ?? null,
                'birth_day'      => $formattedBirthDay ?? null,
                'address'        => $request->input('address') ?? null,
                'country'        => $request->input('country') ?? null,
                'username'       => $request->input('username') ?? explode('@', $request->input('email'))[0],
                'status_working' => $request->input('status_working'),
                'password'       => PASSWORD_ADMIN_SET,
                'status'         => STATUS_IS_ACTIVE,
                'email'          => $request->input('email'),
                'position_id'    => $request->input('position') ?? null,
                'time_off_hours' => (int)$request->input('time_off_hours') ?? LEAVES_HOUR,
                'started_at'     => $formattedStartedAt,
                'ended_at'       => $formattedEndedAt ?? null,
                'leader_id'      => $request->input('leader_id'),
                'gender'         => $request->input('gender'),
            ]);

            $user->departments()->attach($request->input('department_ids'), [
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $user->assignRole(strtolower($request->input('role')));

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Đăng ký thành công',
                'data'    => new UserResource($user)
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'code'    => ERROR,
                'message' => $e->getMessage(),
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    /**
     * Update user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function update(Request $request)
    {
        $id = $request->input('id');

        $validator = Validator::make($request->all(), [
            'idkey'                  => 'required|string|unique:m_users,idkey,' . $id,
            'id'                     => 'required|integer|exists:m_users,id',
            'username'               => 'string|unique:m_users,username,' . $id,
            'email'                  => [
                'required',
                'string',
                'regex:/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/',
                'unique:m_users,email,' . $id
            ],
            'started_at'             => 'required|date_format:d/m/Y',
            'ended_at'               => 'nullable|date_format:d/m/Y|after:started_at',
            'position'               => 'integer|exists:m_positions,id',
            'department_ids'         => 'required|array',
            'department_ids.*'       => 'integer|exists:m_department,id',
            'status_working'         => 'required',
            //            'fullname'         => 'string',
            //            'phone'            => 'sometimes|regex:/^[0-9]+$/|size:10',
            //            'birth_day'        => 'sometimes|date_format:d/m/Y|before:today',
            'updated_at'             => 'required|date_format:Y-m-d H:i:s',
            'time_off_hours'         => 'sometimes|numeric',
            'last_year_time_off'     => 'sometimes|numeric',
            'leader_id'              => 'required|integer|exists:m_users,id',
            'role'                   => 'required',
            'gender'                 => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $departmentIds = $request->input('department_ids');

        if (count($departmentIds) !== count(array_unique($departmentIds))) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Department phải chứa những giá trị không trùng lặp nhau',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        if ($request->filled('birth_day')) {
            $birthDay = \DateTime::createFromFormat('d/m/Y', $request->input('birth_day'));
            $formattedBirthDay = $birthDay->format('Y-m-d');
        }

        $formattedStartedAt = \DateTime::createFromFormat('d/m/Y', $request->input('started_at'))->format('Y-m-d');

        if ($request->filled('ended_at')) {
            $formattedEndedAt = \DateTime::createFromFormat('d/m/Y', $request->input('ended_at'))->format('Y-m-d');
        }

        DB::beginTransaction();
        try {
            $roleRequest = Role::where('name', $request->input('role'))->first();
            $leaderUserChoose = User::where('id', $request->input('leader_id'))->where('status', STATUS_IS_ACTIVE)->first();
            if (!$roleRequest || !$leaderUserChoose) {
                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu không tồn tại role',
                    'data'    => []
                ], CLIENT_ERROR);
            }
            if (!$leaderUserChoose->hasPermissionTo('leave_execute')) {
                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Role không có quyền thực hiện thao tác này',
                    'data'    => []
                ], CLIENT_ERROR);
            }

            $user = User::find($id);

            // check concurrency
            $requestUpdatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $request->input('updated_at'))->getTimestamp();

            $dbUpdatedAt = $user->updated_at->getTimestamp();

            if ($dbUpdatedAt !== $requestUpdatedAt) {
                DB::rollback();

                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu đã được cập nhật bởi người khác. Vui lòng tải lại trang và thử lại.',
                    'data'    => []
                ], CLIENT_ERROR);
            }

            $user->update([
                'idkey'          => $request->input('idkey') ?? $user->idkey,
                'fullname'       => $request->input('fullname') ?? $user->fullname,
                'phone'          => $request->input('phone') ?? $user->phone,
                'birth_day'      => $formattedBirthDay ?? $user->birth_day,
                'address'        => $request->input('address') ?? $user->address,
                'country'        => $request->input('country') ?? $user->country,
                'username'       => $request->input('username') ?? explode('@', $request->input('email'))[0],
                'status_working' => $request->input('status_working'),
                'email'          => $request->input('email'),
                'position_id'    => $request->input('position') ?? $user->position_id,
                'started_at'     => $formattedStartedAt,
                'ended_at'       => $formattedEndedAt ?? null,
                'time_off_hours' => (int)$request->input('time_off_hours') ?? $user->time_off_hours,
                'leader_id'      => $request->input('leader_id'),
                'gender'         => $request->input('gender'),
                'last_year_time_off'  => (int)$request->input('last_year_time_off') ?? $user->last_year_time_off
            ]);

            $user->departments()->detach();
            foreach ($departmentIds as $departmentId) {
                $user->departments()->attach($departmentId, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $user->syncRoles(strtolower($request->input('role')));

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Cập nhật hành công',
                'data'    => new UserResource($user)
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    /**
     * Delete user
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'         => 'required|integer|exists:m_users,id',
            'updated_at' => 'required|date_format:Y-m-d H:i:s',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $id = $request->input('id');
        $user = User::find($id);

        DB::beginTransaction();
        try {
            // check concurrency
            $requestUpdatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $request->input('updated_at'))->getTimestamp();

            $dbUpdatedAt = $user->updated_at->getTimestamp();

            if ($dbUpdatedAt !== $requestUpdatedAt) {
                DB::rollback();

                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu đã được cập nhật bởi người khác. Vui lòng tải lại trang và thử lại.',
                    'data'    => []
                ], CLIENT_ERROR);
            }


            if ($user->status == STATUS_INACTIVE) {
                // TH leader của staff đó cũng Status Inactive, gán admin đang login thành leader của staff đó
                $user->update([
                    'status'         => STATUS_IS_ACTIVE,
                    'leader_id'      => auth()->user()->id,
                ]);
            } elseif ($user->status == STATUS_IS_ACTIVE) {
                if ($user->hasRole('admin')) {
                    // Nếu Admin chỉ có 1 tài khoản duy nhất -> ko cho inactive
                    $countAdmin = User::role('admin')->where('status', STATUS_IS_ACTIVE)->count();
                    if ($countAdmin == 1) {
                        return errorResponse(ONLY_ONE_ACTIVE_ADMIN);
                    }
                    // Admin ko thể inactive chính mình
                    if (auth()->user()->id == $user->id) {
                        return errorResponse(CANNOT_INACTIVATE_YOURSELF);
                    }
                }
                if ($user->hasPermissionTo('leave_execute')) {
                    // TH user là leader của tối thiểu 1 người -> báo lỗi
                    $countUserAppove = User::where('leader_id', $user->id)->where('status', STATUS_IS_ACTIVE)->count();
                    if ($countUserAppove > 0) {
                        return errorResponse(LEADER_OF_ACTIVE_STAFF);
                    }
                }
                $user->update([
                    'status'         => STATUS_INACTIVE,
                ]);
            }

            DB::commit();
            return response()->json([
                'code'    => OK,
                'message' => 'Xóa người dùng thành công',
                'data'    => []
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'code'    => ERROR,
                'message' => 'Có lỗi xảy ra',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    public function checkActiveUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'         => 'required|integer|exists:m_users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $id = $request->input('id');
        $user = User::find($id);

        try {
            if ($user->status == STATUS_INACTIVE) {
                // TH leader của staff đó cũng Status Inactive
                $leaderUser = User::where('id', $user->leader_id)->first();
                if ($leaderUser && $leaderUser->status == STATUS_INACTIVE) {
                    return errorResponse(LEADER_INACTIVE);
                }
            }
            return response()->json([
                'code'    => OK,
                'message' => '',
                'data'    => true
            ], SUCCESS);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Có lỗi xảy ra',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'               => 'required|integer|exists:m_users,id',
            'updated_at'       => 'required|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        DB::beginTransaction();

        try {
            $id = $request->input('id');
            $user = User::find($id);

            // check concurrency
            $requestUpdatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $request->input('updated_at'))->getTimestamp();

            $dbUpdatedAt = $user->updated_at->getTimestamp();

            if ($dbUpdatedAt !== $requestUpdatedAt) {
                DB::rollback();

                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu đã được cập nhật bởi người khác. Vui lòng tải lại trang và thử lại.',
                    'data'    => []
                ], CLIENT_ERROR);
            }

            $user->update([
                'password'         => PASSWORD_ADMIN_SET,
                'password_changed' => false
            ]);

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Cập nhật hành công',
                'data'    => new UserResource($user)
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Có lỗi xảy ra',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    /**
     * Update hide notification hour
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function updateHideNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'hide_notification_to'  => 'required|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        DB::beginTransaction();
        try {
            $user = auth()->user();
            $user->hide_notification_to = $request->input('hide_notification_to');
            $user->save();
            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Cập nhật hành công',
                'data'    => new UserResource($user)
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => []
            ], SERVER_ERROR);
        }
    }
}
