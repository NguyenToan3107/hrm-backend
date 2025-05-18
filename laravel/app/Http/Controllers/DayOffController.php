<?php

namespace App\Http\Controllers;

use App\Http\Resources\DayOffResource;
use App\Models\DayOff;
use App\Rules\Trim;
use App\Rules\UniqueArray;
use App\Utils\Util;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

class DayOffController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:dashboard_view')->only('index', 'show');
        $this->middleware('permission:dashboard_edit')->only('store', 'update', 'destroy');
    }

    /**
     * Lấy danh sách ngày nghỉ từ bảng m_day_offs
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     * @apiBody {integer} [country] Giá trị country để lọc danh sách ngày nghỉ Việt Nam hoặc Nhật Bản
     * @apiBody {integer} [current_year] Giá trị current_year để lọc danh sách ngày nghỉ theo năm
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'create_date'          => 'sometimes|date_format:d/m/Y',
            'day_off_start_date'   => 'sometimes|date_format:d/m/Y',
            'day_off_end_date'     => 'sometimes|date_format:d/m/Y',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors(),
            ], CLIENT_ERROR);
        }

        $dayOffs = DayOff::query();

        if ($request->filled('country')) {
            $country = $request->input('country');
            $dayOffs->where('country', $country);
        } else {
            $dayOffs->where('country', 'VN');
        }

        if ($request->filled('current_year')) {
            $currentYear = $request->input('current_year');
            $dayOffs->whereYear('day_off', $currentYear);
        } else {
            $currentYear = Carbon::now()->year;
            $dayOffs->whereYear('day_off', $currentYear);
        }

        if ($request->filled('status')) {
            $status = explode(",", $request->query('status'));
            $dayOffs->whereIn('status', $status);
        }

        if ($request->filled('create_date')) {
            $createDate = \DateTime::createFromFormat('d/m/Y', $request->query('create_date'));
            $formattedCreateDate = $createDate->format('Y-m-d');
            $dayOffs = $dayOffs->whereDate('created_at', $formattedCreateDate);
        }

        if ($request->filled('day_off_start_date')) {
            $startDate = \DateTime::createFromFormat('d/m/Y', $request->input('day_off_start_date'))->format('Y-m-d');
            $dayOffs = $dayOffs->where('day_off', '>=', $startDate);
        }

        if ($request->filled('day_off_end_date')) {
            $endDate = \DateTime::createFromFormat('d/m/Y', $request->input('day_off_end_date'))->format('Y-m-d');
            $dayOffs = $dayOffs->where('day_off', '<=', $endDate);
        }

        // Sort
        if ($request->filled('sort_by') || $request->filled('sort_order')) {
            $validSortColumns = ['created_at', 'status', 'salary', 'description', 'day_off', 'title'];

            $sortBy = $request->query('sort_by', 'created_at');
            $sortOrder = $request->query('sort_order', 'asc');

            if (!in_array($sortBy, $validSortColumns)) {
                $sortBy = 'created_at';
            }

            $sortOrder = $sortOrder === 'desc' ? 'desc' : 'asc';

            if ($sortBy == "salary") {
                $dayOffs->orderByRaw("salary $sortOrder");
            } elseif ($sortBy === "description") {
                $dayOffs = $dayOffs->orderByRaw("LENGTH(description) $sortOrder");
            } elseif ($sortBy === "title") {
                $dayOffs = $dayOffs->orderByRaw("LENGTH(title) $sortOrder");
            } elseif ($sortBy === "day_off") {
                $dayOffs = $dayOffs->orderByRaw("day_off $sortOrder");
            } else {
                $dayOffs = $dayOffs->orderBy($sortBy, $sortOrder);
            }
        } else {
            $dayOffs = $dayOffs->orderBy('created_at', 'desc');
        }

        // Pagination
        $limit = $request->filled('limit') ? $request->get('limit') : DEFAULT_PAGE_SIZE;
        if ($limit > MAX_DEFAULT_PAGE_SIZE) {
            $limit = MAX_DEFAULT_PAGE_SIZE;
        }
        $dayOffs =  $dayOffs->paginate($limit);

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => DayOffResource::collection($dayOffs),
            'total' => $dayOffs->total(),
        ], SUCCESS);
    }


    /**
     * Thông tin chi tiết vê ngày nghỉ từ bảng m_day_offs
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @apiHeader {String} Authorization Bearer token.
     *
     */
    public function show(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer|exists:m_day_offs,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'ID không hợp lệ hoặc không tồn tại',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $id = $request->input('id');
        $dayOff = DayOff::where('id', $id)->first();
        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => new DayOffResource($dayOff),
        ], SUCCESS);
    }


    /**
     * Register days off (new admin has registration rights) - (register in m_day_offs table)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'string|max:20',
            'day_off'     => 'required|date_format:d/m/Y|unique:m_day_offs,day_off',
            'status'      => 'required|integer',
            'description' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $formattedDayOff = \DateTime::createFromFormat('d/m/Y', $request->input('day_off'))->format('Y-m-d');

        if (DayOff::where('day_off', $formattedDayOff)->exists()) {
            return errorResponse(DAYOFF_IS_EXIST);
        }

        DB::beginTransaction();

        try {
            $dayOff = DayOff::create([
                'title'        => $request->title,
                'description'  => $request->description,
                'day_off'      => $formattedDayOff,
                'status'       => $request->status,
                'country'      => VN,
                'salary'       => PAID_LEAVE,
                'started_at'   => MORNING_START,
                'ended_at'     => AFTERNOON_END
            ]);

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Thành công',
                'data'    => new DayOffResource($dayOff)
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Đăng ký thất bại',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    /**
     * Update days off (update m_day_offs table)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'          => 'required|integer|exists:m_day_offs,id',
            'title'       => 'sometimes|string|max:20',
            'day_off'     => 'required|date_format:d/m/Y',
            'status'      => 'required|integer',
            'description' => 'required|string|max:255',
            'updated_at'  => 'required|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => "",
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $formattedDayOff = \DateTime::createFromFormat('d/m/Y', $request->input('day_off'))->format('Y-m-d');

        // if (DayOff::where('day_off', $formattedDayOff)->exists()) {
        //     return errorResponse(DAYOFF_IS_EXIST);
        // }

        DB::beginTransaction();

        try {
            $id = $request->input('id');
            $dayOff = DayOff::where('id', $id)->where('is_delete', DELETED_N)->first();

            // check concurrency
            $requestUpdatedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $request->input('updated_at'))->getTimestamp();

            $dbUpdatedAt = $dayOff->updated_at->getTimestamp();

            if ($dbUpdatedAt !== $requestUpdatedAt) {
                DB::rollback();

                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu đã được cập nhật bởi người khác. Vui lòng tải lại trang và thử lại.',
                    'data'    => []
                ], CLIENT_ERROR);
            }

            $dayOff->update([
                'title'        => $request->title ?? $dayOff->title,
                'description'  => $request->description,
                'day_off'      => $formattedDayOff,
                'status'       => $request->status,
                'country'      => VN,
                'salary'       => PAID_LEAVE,
                'started_at'   => MORNING_START,
                'ended_at'     => AFTERNOON_END
            ]);

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => "Thành công",
                'data'    => new DayOffResource($dayOff)
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
     * Xóa ngày nghỉ do admin đăng ký (xóa từ bảng m_day_offs)
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @apiHeader {String} Authorization Bearer token.
     */
    public function destroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id'               => 'required|integer|exists:m_day_offs,id',
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
            $id = $request->input('id');
            $dayOff = DayOff::where('id', $id)->where('is_delete', DELETED_N)->first();

            // check concurrency
            $requestDeletedAt = \DateTime::createFromFormat('Y-m-d H:i:s', $request->input('updated_at'))->getTimestamp();

            $dbUpdatedAt = $dayOff->updated_at->getTimestamp();

            if ($dbUpdatedAt !== $requestDeletedAt) {
                DB::rollback();

                return response()->json([
                    'code'    => OUT_DATE,
                    'message' => 'Dữ liệu đã được cập nhật bởi người khác. Vui lòng tải lại trang và thử lại.',
                    'data'    => []
                ], CLIENT_ERROR);
            }

            $dayOff->delete();

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => "Xóa thành công",
                'data'    => []
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Xóa thất bại',
                'data'    => []
            ], SERVER_ERROR);
        }
    }
}
