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
        $dayOffs = DayOff::select(
            'id',
            'title',
            'description',
            'started_at',
            'ended_at',
            'day_off',
            'status',
            'salary',
            'country'
        );

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

        $dayOffs = $dayOffs->get();
        $total = $dayOffs->count();

        return response()->json([
            'message' => 'Thành công',
            'data' => [
                'total' => $total,
                'dayOffs' => $dayOffs
            ]
        ], 200);
    }


    /**
     * Thông tin chi tiết vê ngày nghỉ từ bảng m_day_offs
     *
     * @param $id
     * @return \Illuminate\Http\JsonResponse
     * @apiHeader {String} Authorization Bearer token.
     *
     */
    public function show($id)
    {
        $messages = [
            'required' => 'Yêu cầu :attribute là bắt buộc',
            'integer' => 'Yêu cầu :attribute phải là một số nguyên',
            'exists' => 'Không tồn tại ngày phép với id được tìm'
        ];

        $validator = Validator::make(['id' => $id], [
            'id' => 'required|integer|exists:m_day_offs'
        ], $messages);

        if ($validator->fails()) {
            return response()->json([
                'status' => 422,
                'message' => $validator->errors()
            ], 422);
        }

        $dayOff = DayOff::where('id', $id)
            ->select('id', 'title', 'description', 'started_at', 'ended_at', 'day_off', 'status', 'salary', 'country')
            ->first();


        return response()->json([
            'message' => 'Thành công',
            'data' => $dayOff
        ], Response::HTTP_OK);
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
