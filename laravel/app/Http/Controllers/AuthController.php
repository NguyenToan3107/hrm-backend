<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Mockery\Exception;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function __construct()
    {
    }

    /**
     * Đăng nhập user vào hệ thống
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function login()
    {
        $validator = Validator::make(request()->all(), [
            'email'            => [
                'required',
                'string',
                'regex:/^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/',
            ],
            'password'         => [
                'required',
                'string',
                'regex:/^(?=.*[a-zA-Z])(?=.*\d).{8,}$/',
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Thiếu thông tin đăng nhập',
                'data'    => $validator->errors()
            ], CLIENT_ERROR);
        }

        $credentials = request(['email', 'password']);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Email không tồn tại',
                'data'    => [
                    'email' => [
                        EMAIL_INCORRECT
                    ]
                ]
            ], CLIENT_ERROR);
        }

        if($user->status == STATUS_INACTIVE){
            return response()->json([
                'code'    => ERROR,
                'message' => '',
                'data'    => [
                    'status' => [
                        ACCOUNT_IS_INACTIVE
                    ]
                ]
            ], CLIENT_ERROR);
        }

        if (!$token = auth()->attempt($credentials)) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Mật khẩu không chính xác',
                'data'    => [
                    'password' => [
                        PASSWORD_INCORRECT
                    ]
                ]
            ], CLIENT_ERROR);
        }

        $refreshToken = $this->createRefreshToken();

        return $this->respondWithToken($token, $refreshToken);
    }


    /**
     * Đăng ký user vào hệ thống
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signup(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'username' => 'required|string|unique:m_users',
                'email' => 'required|string|email|unique:m_users',
                'password' => 'required|string|min:8|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dữ liệu không hợp lệ',
                    'errors' => $validator->errors()
                ], 400);
            }

            $user = User::create([
                'leader_id' => $request->input('leader_id'),
                'username' => $request->input('username'),
                'fullname' => $request->input('fullname'),
                'email' => $request->input('email'),
                'password' => $request->input('password'),
                'address' => $request->input('address'),
                'phone' => $request->input('phone'),
                'birth_day' => $request->input('birth_day'),
                'status' => $request->input('status'),
                'status_working' => $request->input('status_working'),
                'time_off_hours' => $request->input('time_off_hours'),
            ]);
            return response()->json([
                'status' => 200,
                'message' => 'Đăng ký thành công',
                'data' => new UserResource($user)
            ], 200);
        } catch (Exception $exception) {
            return response()->json([
                'message' => '',
                'data' => $exception->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

    }


    /**
     * Lấy thông tin user khi đăng nhập
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     */
    public function profile()
    {
        try {
            return response()->json([
                'code'    => OK,
                'message' => 'Thành công',
                'data'    => auth()->user()
            ], SUCCESS);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }


    /**
     * Đăng xuất khỏi hệ thống
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     *
     */
    public function logout()
    {
        auth()->logout();

        return response()->json([
            'code'    => OK,
            'message' => 'Đăng xuất thành công',
            'data'    => []
        ], SUCCESS);
    }


    /**
     * Cấp lại access token khi hết hạn, không hợp lệ
     *
     * @return \Illuminate\Http\JsonResponse
     *
     * @apiHeader {String} Authorization Bearer token.
     * @apiBody {String} refresh_token Refresh token hợp lệ để cấp lại access token.
     */
    public function refresh()
    {
//        try {
//            if (!JWTAuth::parseToken()->check()) {
//                return response()->json(['error' => 'Access token không hợp lệ hoặc hết hạn'], 401);
//            }
//        } catch (JWTException $e) {
//            return response()->json(['error' => 'Access token không hợp lệ'], 401);
//        }

        $refreshToken = request()->input('refresh_token');

        if (!$refreshToken) {
            return response()->json(['message' => 'Refresh token không được cung cấp'], 400);
        }

        try {
            $decoded = JWTAuth::getJWTProvider()->decode($refreshToken);
            $user = User::find($decoded['user_id']);

            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }
//            auth()->invalidate();
            $token = auth()->login($user);
            $refreshToken = $this->createRefreshToken();

            return $this->respondWithToken($token, $refreshToken);

        } catch (JWTException $ex) {
            return response()->json(['message' => 'Refresh Token Invalid'], 500);
        }

        // refresh method ở đây là nó tạo token mới và vô hiệu hóa token cũ (blacklist)
//        return $this->respondWithToken(auth()->refresh());
    }

    /**
     *
     * Thay đổi mật khẩu
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password'   => 'required|string|min:8|max:255',
            'new_password'       => 'required|string|min:8|max:255|regex:/^(?=.*[a-zA-Z])(?=.*\d).{8,}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'code'           => ERROR,
                'message'        => "",
                'data'           => $validator->errors()
            ], CLIENT_ERROR);
        }

        if (!Hash::check($request->current_password, Auth::user()->password)) {
            return response()->json([
                'code'           => ERROR,
                'message'        => 'Mật khẩu hiện tại nhập không chính xác',
                'data'           => [
                    'current_password' => [
                        CURRENT_PASSWORD_INCORRECT
                    ]
                ]
            ], CLIENT_ERROR);
        }

        if ($request->input('current_password') != $request->input('new_password')) {
            Auth::user()->update([
                'password'         => Hash::make($request->new_password),
                'password_changed' => true
            ]);
        } else {
            return response()->json([
                'code'      => ERROR,
                'message'   => 'Mật khẩu mới không được giống mật khẩu cũ',
                'data'      => [
                    'current_password' => [
                        PASSWORD_NOT_SAME
                    ]
                ]
            ], CLIENT_ERROR);
        }

        return response()->json([
            'code'      => OK,
            'message'   => 'Đổi mật khẩu thành công',
            'data'      => []
        ], SUCCESS);
    }

    private function respondWithToken($token, $refreshToken)
    {
        return response()->json([
            'code'    => OK,
            'message' => 'Thành công',
            'data'    => [
                'access_token'  => $token,
                'refresh_token' => $refreshToken,
                'token_type'    => 'bearer',
                'expires_in'    => auth()->factory()->getTTL() * 60,
            ],
        ], SUCCESS);
    }

    private function createRefreshToken()
    {
        $data = [
            'user_id' => auth()->id(),
            'random'  => rand() . time(),
            'exp'     => time() + config('jwt.refresh_ttl'),
        ];
        $refreshToken = JWTAuth::getJWTProvider()->encode($data);

        return $refreshToken;
    }
}
