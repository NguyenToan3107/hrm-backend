<?php

namespace App\Http\Controllers;

use App\Http\Resources\DayOffResource;
use App\Http\Resources\LeaveResource;
use App\Models\DayOff;
use App\Utils\Util;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class CalendarController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:dashboard_view');
    }

    /**
     * Hàm in ra lịch của 1 năm cho nhân viên
     *
     * @return JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     * @apiBody {integer} [country] Giá trị country để lọc danh sách ngày nghỉ Việt Nam hoặc Nhật Bản
     * @apiBody {integer} [current_year] Giá trị year để lọc danh sách ngày nghỉ theo năm
     *
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'current_year'  => 'sometimes|integer|digits:4',
            'country'       => 'sometimes|string|size:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors(),
            ], CLIENT_ERROR);
        }

        if ($request->filled('current_year')) {
            $currentYear = $request->input('current_year');
        } else {
            $currentYear = Carbon::now()->year;
        }

        if ($request->filled('country')) {
            $country = $request->input('country');
        } else {
            $country = VN;
        }

        $user = auth()->user();

        // Day Off
        $dayOffs = DayOff::whereYear('day_off', $currentYear)
            ->where('country', $country)
            ->where('is_delete', DELETED_N)
            ->orWhere(function ($query) use ($currentYear) {
                $query->whereYear('day_off', $currentYear - 1)
                    ->whereMonth('day_off', 12);
            })
            ->orWhere(function ($query) use ($currentYear) {
                $query->whereYear('day_off', $currentYear + 1)
                    ->whereMonth('day_off', 1);
            })
            ->get();

        // Leaves
        $leaves = $user->leaves()
            ->whereYear('day_leave', $currentYear)
            //            ->where('country', $country)
            ->where('is_delete', DELETED_N)
            ->get();

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => [
                'current_year' => $currentYear,
                'day_offs'     => DayOffResource::collection($dayOffs),
                'leaves'       => LeaveResource::collection($leaves)
            ],
        ], SUCCESS);
    }

    /**
     * Hàm lấy ra ngày đặc biệt (ngày nghỉ, ngày làm bù) trong khoảng 14 ngày từ ngày hiện tại.
     *
     * @return JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     * @apiBody {integer} [country] Giá trị country để lọc danh sách ngày nghỉ Việt Nam hoặc Nhật Bản
     *
     */
    public function getSpecialDayIn14Day(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'country'       => 'sometimes|string|size:2'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors(),
            ], CLIENT_ERROR);
        }

        $currentDate = Carbon::parse(Carbon::now())->format('Y-m-d');
        $endDate = Carbon::parse(Carbon::now()->addDays(14))->format('Y-m-d');

        if ($request->filled('country')) {
            $country = $request->input('country');
        } else {
            $country = VN;
        }

        $user = auth()->user();

        // Day Off
        $dayOffs = DayOff::where('day_off', '>=', $currentDate)
            ->where('day_off', '<=', $endDate)
            ->where('country', $country)
            ->where('is_delete', DELETED_N)
            ->orderBy('day_off', 'ASC')
            ->get();

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => [
                'day_offs'     => DayOffResource::collection($dayOffs)
            ],
        ], SUCCESS);
    }
}
