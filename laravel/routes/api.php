<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CommonController;
use App\Http\Controllers\DayOffController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\MyPageController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ZKTecoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/
//
//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::prefix('/api/v1/')->middleware(['api'])->group(function () {
    ////////////////////////////  AUTH
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/signup', [AuthController::class, 'signup']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::get('/zkteco/connect', [ZKTecoController::class, 'connect']);
    Route::get('/zkteco/users', [ZKTecoController::class, 'getUsers']);
    Route::get('/zkteco/attendance', [ZKTecoController::class, 'getAttendance']);


    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);

        ////////////////////////////  USERS
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/detail', [UserController::class, 'show']);
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users', [UserController::class, 'update']);
        Route::delete('/users', [UserController::class, 'destroy']);
        Route::get('/users/status', [UserController::class, 'checkActiveUser']);
        Route::post('/users/reset-password', [UserController::class, 'resetPassword']);
        Route::post('/users-update-hide-notification', [UserController::class, 'updateHideNotification']);

        ////////////////////////////  MY PAGE
        Route::get('/my-page', [MyPageController::class, 'index']);
        Route::post('/my-page', [MyPageController::class, 'update']);
        Route::get('/my-page/leave', [MyPageController::class, 'showLeave']);

        ////////////////////////////  CALENDAR
        Route::get('/calendar', [CalendarController::class, 'index']);
        Route::get('/getdayfornotification', [CalendarController::class, 'getSpecialDayIn14Day']);

        ////////////////////////////  DAY OFF
        Route::get('/day-off', [DayOffController::class, 'index']);
        Route::get('/day-off/{id}', [DayOffController::class, 'show']);
        Route::post('/day-off', [DayOffController::class, 'store']);
        Route::put('/day-off', [DayOffController::class, 'update']);
        Route::delete('/day-off', [DayOffController::class, 'destroy']);

        ////////////////////////////  LEAVE
        Route::get('/leaves', [LeaveController::class, 'index']);
        Route::get('/leaves/detail', [LeaveController::class, 'show']);
        Route::post('/leaves', [LeaveController::class, 'store']);
        Route::post('/leaves/edit', [LeaveController::class, 'update']);
        Route::post('/leaves/confirm', [LeaveController::class, 'confirm']);
        Route::post('/leaves/cancel', [LeaveController::class, 'cancel']);
        Route::post('/leaves/cancel-request', [LeaveController::class, 'cancelRequest']);
        Route::post('/leaves/skip-cancel-request', [LeaveController::class, 'skipCancelRequest']);
        Route::post('/leaves/admin-create-leave', [LeaveController::class, 'adminCreateLeave']);

        ////////////////////////////  POSITION
        Route::get('/positions', [PositionController::class, 'index']);

        ////////////////////////////  DEPARTMENT
        Route::get('/departments', [DepartmentController::class, 'index']);

        ////////////////////////////  COMMON
        Route::get('/commons', [CommonController::class, 'index']);
        Route::get('/commons/users', [CommonController::class, 'show']);
        Route::get('/commons/users/admin-leader', [CommonController::class, 'showAdminLeader']);
        Route::get('/commons/users/department/admin-leader', [CommonController::class, 'showAdminLeaderOfDepartment']);
        Route::get('/commons/users/detail', [CommonController::class, 'showDetail']);
        Route::get('/commons/export-pdf', [CommonController::class, 'export']);
        Route::get('/commons/roles', [CommonController::class, 'listRoles']);

        ////////////////////////////  Roles
        Route::get('/roles', [RoleController::class, 'index']);
        Route::get('/roles/show', [RoleController::class, 'show']);
        Route::post('/roles/create', [RoleController::class, 'store']);
        Route::get('/roles/list-permissions', [RoleController::class, 'listPermissions']);
        Route::post('/roles/edit', [RoleController::class, 'edit']);
        Route::delete('/roles/delete', [RoleController::class, 'delete']);

        ////////////////////////////  Reports
        Route::get('/reports', [ReportController::class, 'index']);
        Route::get('/reports/export-pdf', [ReportController::class, 'export']);
        Route::get('/reports/check', [ReportController::class, 'checkReport']);
    });
});
