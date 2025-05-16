<?php

namespace App\Http\Controllers;

use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:role_master')->except('index');
    }

    /**
     * Get all role
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function index(Request $request)
    {
        // get user status active
        // $roles = Role::withCount(['users' => function ($query) {
        //     $query->where('status', STATUS_IS_ACTIVE);
        // }]);
        $roles = Role::withCount('users');
        // Search: name
        if ($request->filled('name')) {
            $name = $request->query('name');
            $roles->where('name', 'LIKE', "%{$name}%");
        }

        // Sort
        if ($request->filled('sort_by') || $request->filled('sort_order')) {
            $validSortColumns = ['name', 'description', 'employee_number'];

            $sortBy = $request->query('sort_by', 'name');
            $sortOrder = $request->query('sort_order', 'asc');

            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'name';
            }

            $sortOrder = $sortOrder === 'desc' ? 'desc' : 'asc';

            if ($sortBy === "name") {
                $roles = $roles->orderByRaw("SUBSTRING_INDEX(name, ' ', -1) $sortOrder");
            } elseif ($sortBy === "description") {
                $roles = $roles->orderByRaw("LENGTH(description) $sortOrder");
            } elseif ($sortBy === "employee_number") {
                $roles = $roles->orderByRaw("users_count $sortOrder");
            }
        }
        $limit = $request->filled('limit') ? $request->get('limit') : DEFAULT_PAGE_SIZE;
        if ($limit > MAX_DEFAULT_PAGE_SIZE) {
            $limit = MAX_DEFAULT_PAGE_SIZE;
        }
        // Pagination
        $roles = $roles->paginate($limit);
        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => RoleResource::collection($roles),
            'total'   => $roles->total(),
        ], SUCCESS);
    }

    public function show(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:m_roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'ID không hợp lệ hoặc không tồn tại',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $id = $request->input('id');
        $role = Role::withCount('users')->find($id);
        $permissions = $role->permissions;

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => [
                'role'        => new RoleResource($role),
                'permissions' => PermissionResource::collection($permissions),
            ]
        ], SUCCESS);
    }

    /**
     * Create role
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fullname'      => 'required|string|unique:m_roles,name',
            'description'   => 'required|string',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'integer|exists:m_permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'      => ERROR,
                'message'   => '',
                'data'      => $validator->errors()
            ], CLIENT_ERROR);
        }

        DB::beginTransaction();

        try {
            $role = Role::create([
                'name'          => mb_strtolower($request->get('fullname')),
                'description'   => $request->get('description'),
                'guard_name'    => 'api',
                'role_name'     => ucwords($request->get('fullname'))
            ]);
            if ($request->filled('permissions')) {
                $permissions = Permission::whereIn('id', $request->get('permissions'))->get();
                $role->syncPermissions($permissions);
            }
            $permissions = $role->permissions;

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Thành công',
                'data'    => [
                    'role'        => new RoleResource($role),
                    'permissions' => PermissionResource::collection($permissions),
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

    public function listPermissions()
    {
        $permissions = Permission::all();
        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => PermissionResource::collection($permissions),
            'total'   => $permissions->count()
        ], SUCCESS);
    }

    /**
     * Edit role
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'            => 'required|integer|exists:m_roles,id',
            'fullname'      => 'nullable|string|unique:m_roles,name,' . $request->id,
            'description'   => 'nullable|string',
            'permissions'   => 'nullable|array',
            'permissions.*' => 'integer|exists:m_permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'      => ERROR,
                'message'   => '',
                'data'      => $validator->errors()
            ], CLIENT_ERROR);
        }

        DB::beginTransaction();

        try {
            $role = Role::findById($request->input('id'));
            $role->fill([
                'name'          => mb_strtolower($request->input('fullname')),
                'description'   => $request->input('description'),
                'role_name'     => ucwords($request->input('fullname'))
            ])->save();

            if ($request->filled('permissions')) {
                $permissions = Permission::whereIn('id', $request->get('permissions'))->get();
                $role->syncPermissions($permissions);
            }

            $permissions = $role->permissions;

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Thành công',
                'data'    => [
                    'role'        => new RoleResource($role),
                    'permissions' => PermissionResource::collection($permissions),
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
     * Delete role
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'               => 'required|integer|exists:m_roles,id',
            'updated_at'       => 'required|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        DB::beginTransaction();
        try {
            $role = Role::findById($request->input('id'));

            // check concurrency
            $requestUpdatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $request->input('updated_at'))->getTimestamp();
            $dbUpdatedAt = $role->updated_at->getTimestamp();

            if ($dbUpdatedAt !== $requestUpdatedAt) {
                DB::rollback();

                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu đã được cập nhật bởi người khác. Vui lòng tải lại trang và thử lại.',
                    'data'    => []
                ], CLIENT_ERROR);
            }
            // check exist user
            $userCount = $role->users()->count();
            if ($userCount > 0) {
                DB::rollback();

                return errorResponse(EXIST_USER_OF_ROLE);
            }

            // delete role and permission
            $role->delete();
            $role->permissions()->delete();

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Thành công',
                'data'    => []
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
}
