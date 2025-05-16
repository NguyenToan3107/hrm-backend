<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeaveUserResource;
use App\Http\Resources\UserResource;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use function Symfony\Component\String\u;

class MyPageController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:mypage');
    }

    /**
     * Get logged in user information
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $user = auth()->user();

        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => new UserResource($user)
        ], SUCCESS);
    }


    /**
     * Update logged in user information
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fullname'         => 'required|string|min:1|max:255',
            'phone'            => 'required|regex:/^[0-9]+$/|size:10',
            'birth_day'        => 'required|date_format:d/m/Y|before:today',
            'address'          => 'required|string|min:1|max:255',
            'country'          => 'required|string|min:1|max:255',
            'updated_at'       => 'required|date_format:Y-m-d H:i:s',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Dữ liệu không hợp lệ',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $birthDay = \DateTime::createFromFormat('d/m/Y', $request->input('birth_day'));
        $formattedBirthDay = $birthDay->format('Y-m-d');

        DB::beginTransaction();
        try {
            $user = auth()->user();

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

            $imagePath = $user->image;
            if ($request->hasFile('image')) {
                if ($imagePath && file_exists(public_path($imagePath))) {
                    unlink(public_path($imagePath));  // Delete old image
                }

                // Save new image
                $file = $request->file('image');
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $newFileName = $user->id . '-' . pathinfo($originalName, PATHINFO_FILENAME) . '.' . $extension; // VD: 477097-1.jpg
                $path = $file->storeAs('photos/users/' . $user->id, $newFileName, 'public');
                $imagePath = '/storage/' . $path;
            }

            $imagePathRoot = $user->image_root;
            if ($request->hasFile('image_root')) {
                if ($imagePathRoot && file_exists(public_path($imagePathRoot))) {
                    unlink(public_path($imagePathRoot));  // Delete old image
                }

                // Save new image
                $file = $request->file('image_root');
                $originalName = $file->getClientOriginalName();
                $extension = $file->getClientOriginalExtension();
                $newFileName = $user->id . '-' . pathinfo($originalName, PATHINFO_FILENAME) . '-root' . '.' . $extension; // VD: 477097-1-root.jpg
                $path = $file->storeAs('photos/users/' . $user->id, $newFileName, 'public');
                $imagePathRoot = '/storage/' . $path;
            }

            $user->update([
                'fullname'       => $request->input('fullname'),
                'phone'          => $request->input('phone'),
                'birth_day'      => $formattedBirthDay,
                'address'        => $request->input('address'),
                'country'        => $request->input('country'),
                'image'          => $imagePath,
                'image_root'     => $imagePathRoot,
            ]);

            DB::commit();

            return response()->json([
                'code'    => OK,
                'message' => 'Cập nhật hành công',
                'data'    => new UserResource($user),
            ], SUCCESS);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'code'    => ERROR,
                'message' => 'Có lỗi xảy ra',
                'data'    => []
            ], SERVER_ERROR);
        }
    }

    /**
     * Get my leaves
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function showLeave(Request $request)
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
        $user = User::find($id);
        $leaves = $user->leaves()->where('is_delete', DELETED_N);

        // Sort (default sort by created desc)
        $sortBy = $request->query('sort_by', 'created_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $validSortColumns = ['created_at', 'status', 'day_leave'];

        if (!in_array($sortBy, $validSortColumns)) {
            $sortBy = 'created_at';
        }
        $sortOrder = $sortOrder === 'desc' ? 'desc' : 'asc';

        $leaves = $leaves->orderBy($sortBy, $sortOrder);

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
}
