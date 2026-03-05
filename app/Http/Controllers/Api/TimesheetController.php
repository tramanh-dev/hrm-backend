<?php

namespace App\Http\Controllers\Api;

use App\Exports\MonthlyTimesheetExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Timesheet;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class TimesheetController extends Controller
{
    /**
     * [HR ONLY] Xem chấm công của toàn bộ nhân viên trong tháng
     */
    public function getAllTimesheets()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if ($user->role !== 'HR') {
            return response()->json(['message' => 'Bạn không có quyền này!'], 403);
        }

        $today = now();

        if ($today->day >= 26) {
            $fromDate = now()->startOfMonth()->day(26);
        } else {
            $fromDate = now()->subMonth()->startOfMonth()->day(26);
        }

        $toDate = $today;

        $timesheets = Timesheet::with('user:id,name,email,avatar')
            ->whereBetween('work_date', [$fromDate->toDateString(), $toDate->toDateString()])
            ->orderBy('work_date', 'desc')
            ->orderBy('check_in', 'asc')
            ->get();


        return response()->json($timesheets);
    }

    /**
     * [EMPLOYEE] Xem lịch sử chấm công cá nhân
     */
    public function index()
    {
        $user = Auth::user();
        $currentMonth = now()->month;
        $currentYear = now()->year;
        $today = now()->toDateString();

        $timesheets = Timesheet::where('user_id', $user->id)
            ->whereMonth('work_date', $currentMonth)
            ->whereYear('work_date', $currentYear)
            ->orderBy('work_date', 'desc')
            ->get();

        // Tính tổng công dựa trên số công thực tế (day_count)
        $actualWorkDays = $timesheets->sum('day_count');

        $todayRecord = Timesheet::where('user_id', $user->id)
            ->where('work_date', $today)
            ->first();

        return response()->json([
            'standard_days' => 25,
            'actual_days' => $actualWorkDays,
            'today_record' => $todayRecord,
            'history' => $timesheets
        ]);
    }

    /**
     * [EMPLOYEE] Thực hiện Check-in
     */
    public function checkIn()
    {
        $user = Auth::user();
        $today = now()->toDateString();
        $nowTime = now()->toTimeString();

        $exists = Timesheet::where('user_id', $user->id)->where('work_date', $today)->exists();
        if ($exists) {
            return response()->json(['message' => 'Hôm nay bạn đã check-in rồi!'], 400);
        }

        Timesheet::create([
            'user_id' => $user->id,
            'work_date' => $today,
            'check_in' => $nowTime,
            'day_count' => 0
        ]);

        return response()->json(['message' => 'Check-in thành công! Bắt đầu tính giờ làm.']);
    }

    /**
     * [EMPLOYEE] Check-out và tự động tính công (0.5 hoặc 1)
     */
    public function checkOut()
    {
        $user = Auth::user();
        $today = now()->toDateString();
        $checkOutTime = now();

        $record = Timesheet::where('user_id', $user->id)->where('work_date', $today)->first();

        if (!$record) {
            return response()->json(['message' => 'Bạn chưa check-in, không thể check-out!'], 400);
        }

        $checkInTime = Carbon::parse($record->check_in);
        $hoursWorked = $checkOutTime->diffInMinutes($checkInTime) / 60;

        $count = 0;
        if ($hoursWorked >= 7) {
            $count = 1.0;
        } elseif ($hoursWorked >= 3.5) {
            $count = 0.5;
        }

        $record->update([
            'check_out' => $checkOutTime->toTimeString(),
            'day_count' => $count
        ]);

        return response()->json([
            'message' => "Check-out thành công! Làm " . round($hoursWorked, 1) . " giờ ($count công)."
        ]);
    }

    /**
     * [HR ONLY] Xuất file Excel để gửi cho phòng Kế toán tính lương
     */

    public function exportExcel(Request $request)
    {
        $user = Auth::user();

        // Cho HR + ADMIN
        if (!in_array(strtolower($user->role), ['hr', 'admin'])) {
            return response()->json(['message' => 'Bạn không có quyền!'], 403);
        }

        /**
         * Logic kỳ công:
         * 26 tháng trước -> 25 tháng này
         */
        $fromDate = Carbon::now()->subMonth()->day(26);
        $toDate   = Carbon::now()->day(25);

        return Excel::download(
            new MonthlyTimesheetExport($fromDate, $toDate),
            'bang_cong_tu_' . $fromDate->format('d_m_Y') . '_den_' . $toDate->format('d_m_Y') . '.xlsx'
        );
    }
}
