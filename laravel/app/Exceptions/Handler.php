<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
        });
    }

    public function render($request, Throwable $exception)
    {
        //        if ($exception instanceof NotFoundHttpException) {
        //            return response()->json([
        //                'code'    => ERROR,
        //                'message' => 'Đường dẫn không tồn tại',
        //                'data'    => []
        //            ], NOT_FOUND);
        //        } elseif ($exception instanceof MethodNotAllowedHttpException) {
        //            return response()->json([
        //                'code'    => ERROR,
        //                'message' => 'Phương thức không được phép',
        //                'data'    => []
        //            ], CLIENT_ERROR);
        //        } elseif ($exception instanceof AuthenticationException) {
        //            return response()->json([
        //                'code'    => ERROR,
        //                'message' => 'Token hết hạn hoặc không hợp lệ',
        //                'data'    => []
        //            ], AUTHEN_ERROR);
        //        } elseif ($exception instanceof RouteNotFoundException) {
        //            return response()->json([
        //                'code'    => ERROR,
        //                'message' => 'Đường dẫn không tồn tại',
        //                'data'    => []
        //            ], NOT_FOUND);
        //        } elseif ($exception instanceof UnauthorizedException) {
        //            return response()->json([
        //                'code'    => ERROR,
        //                'message' => 'User does not have the right roles.',
        //                'data'    => []
        //            ], FORBIDDEN);
        //        }
        //        elseif ($exception instanceof ModelNotFoundException) {
        //            return response()->json([
        //                'message' => 'Đường dẫn không tồn tại',
        //                'data'=> []
        //            ], Response::HTTP_NOT_FOUND);
        //        }
        //
        //        else {
        //            return response()->json([
        //                'code'    => SERVER_ERROR,
        //                'message' => 'Đã xảy ra lỗi không mong muốn',
        //                'data'    => []
        //            ], SERVER_ERROR);
        //        }

        if ($exception instanceof UnauthorizedException) {
            return response()->json([
                'code'    => ERROR,
                'message' => 'Bạn không có quyền truy cập vào tài nguyên này.',
                'data'    => [
                    'FORBIDDEN' => $exception->getMessage()
                ],
            ], FORBIDDEN);
        }

        return parent::render($request, $exception);
    }
}
