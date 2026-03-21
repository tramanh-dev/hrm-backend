<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\TimesheetController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\HrmController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Artisan;

Route::get('/migrate-now', function () {
    Artisan::call('migrate', ["--force" => true]);
    return "Migration done!";
});
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard-stats', [HrmController::class, 'getStats']);

    // 1. Hồ sơ cá nhân
    Route::get('/user-profile', function (Request $request) {
        return $request->user()->load('department');
    });
    Route::put('/profile', [EmployeeController::class, 'updateProfile']);
    Route::post('/upload-avatar', [EmployeeController::class, 'uploadAvatar']);

    // 2. AI + GPS
    Route::prefix('attendance')->group(function () {
        Route::post('/verify-location', [AttendanceController::class, 'verifyLocation']);
        Route::post('/check-in', [AttendanceController::class, 'checkIn']);
        Route::post('/check-out', [AttendanceController::class, 'checkOut']);
        Route::post('/register-face', [AttendanceController::class, 'registerFace']);
    });

    // 3. Lịch sử bảng công (Dành cho nhân viên & HR)
    Route::get('/my-timesheets', [TimesheetController::class, 'index']);
    Route::get('/hr/timesheets', [TimesheetController::class, 'getAllTimesheets']);
    Route::get('/export-timesheet', [TimesheetController::class, 'exportExcel']);

    // 4. Quản lý nhân viên (HR ONLY)
    Route::get('/employees', [HrmController::class, 'getEmployees']);
    Route::get('/employees-summary', [HrmController::class, 'getEmployeesSummary']);
    Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

    // 5. Quản lý phòng ban
    Route::get('/departments', [DepartmentController::class, 'index']);
    Route::post('/departments', [DepartmentController::class, 'store']);
    Route::put('/departments/{id}', [DepartmentController::class, 'update']);
    Route::delete('/departments/{id}', [DepartmentController::class, 'destroy']);

    // 6. Quản lý công việc (Tasks)
    Route::get('/tasks', [TaskController::class, 'index']);
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::get('/my-tasks', [TaskController::class, 'myTasks']);
    Route::get('/hr-tasks', [HrmController::class, 'hrTasks']);
    Route::put('/tasks/{id}/pin', [TaskController::class, 'togglePin']);
    Route::post('/tasks/{id}/assign', [TaskController::class, 'assignUsers']);
    Route::get('/employees/{id}/tasks', [TaskController::class, 'getTasksByEmployee']);
    Route::get('/tasks/{id}/comments', [TaskController::class, 'getComments']);
    Route::post('/tasks/{id}/comments', [TaskController::class, 'postComment']);
    Route::post('/tasks/{id}/report', [HrmController::class, 'submitReport']);

    // 7. Quản lý nghỉ phép (Leaves)
    Route::get('/leaves', [LeaveController::class, 'index']);
    Route::post('/leaves', [LeaveController::class, 'store']);
    Route::get('/all-leaves', [LeaveController::class, 'allLeaves']);
    Route::put('/leaves/{id}/status', [LeaveController::class, 'updateStatus']);

    // 8. Quản lý Lương & Phiếu lương (Payroll)
    Route::get('/payroll/draft', [PayrollController::class, 'getMonthlyDraft']);
    Route::post('/payroll/store', [PayrollController::class, 'storePayslip']);
    Route::get('/my-payslips', [PayrollController::class, 'getMyPayslips']);
    Route::get('/payroll/export-pdf/{id}', [PayrollController::class, 'exportPDF']);
});
