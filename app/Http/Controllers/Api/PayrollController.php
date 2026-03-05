<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Payslip;
use App\Models\Leave;
use App\Models\Timesheet;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class PayrollController extends Controller
{

    public function getMonthlyDraft(Request $request)
    {
        // 1. Lấy tham số tháng/năm từ request, mặc định là hiện tại
        $month = $request->query('month', date('m'));
        $year = $request->query('year', date('Y'));
        $finePerMinute = 2000; // Mức phạt đi trễ 2k/phút

        // 2. Lấy danh sách nhân viên kèm các dữ liệu liên quan trong tháng đó
        $users = User::with([
            'salaryLevel',
            'timesheets' => function ($query) use ($month, $year) {
                $query->whereMonth('work_date', $month)->whereYear('work_date', $year);
            },
            'leaves' => function ($query) use ($month, $year) {
                $query->where('status', 'approved') // Chỉ tính đơn đã duyệt
                    ->whereMonth('start_date', $month)
                    ->whereYear('start_date', $year);
            }
        ])->get();

        // 3. Duyệt qua từng nhân viên để tính toán lương dự thảo
        $data = $users->map(function ($user) use ($month, $year, $finePerMinute) {
            $baseSalary = $user->salaryLevel->base_salary ?? 0;

            // --- PHẦN 1: TÍNH TỔNG NGÀY CÔNG HƯỞNG LƯƠNG ---
            // Tổng ngày đi làm thực tế (từ bảng Timesheet)
            $actualWorkDays = $user->timesheets->sum('day_count');

            // Tổng ngày nghỉ có phép (từ bảng Leaves - đảm bảo cột duration_days có dữ liệu)
            // Chỗ này giúp nhân viên nghỉ phép mà vẫn không bị trừ lương
            $paidLeaveDays = $user->leaves->filter(function ($leave) {
                return in_array($leave->leave_type, ['Nghỉ phép năm', 'Nghỉ lễ', 'Nghỉ phép']);
            })->sum('duration_days');

            // TỔNG NGÀY TÍNH LƯƠNG = Đi làm + Nghỉ có phép
            $totalPayableDays = $actualWorkDays + $paidLeaveDays;

            // Tính lương theo ngày công (Giả định tháng tiêu chuẩn 26 ngày)
            $salaryByDays = ($totalPayableDays > 0) ? ($baseSalary / 26) * $totalPayableDays : 0;

            // --- PHẦN 2: TÍNH PHẠT ĐI TRỄ (Logíc ân hạn 5 phút) ---
            $totalLateMinutes = 0;
            foreach ($user->timesheets as $sheet) {
                if ($sheet->check_in) {
                    $checkInTime = Carbon::parse($sheet->check_in);
                    $hour = (int)$checkInTime->format('H');

                    // Mốc giờ bắt đầu: Sáng 8h, nếu làm nửa ngày chiều thì tính từ 13h (ví dụ)
                    $expectedStart = ($sheet->day_count <= 0.5 && $hour >= 12) ? '13:00:00' : '08:00:00';
                    $standardStart = Carbon::createFromFormat('H:i:s', $expectedStart);
                    $currentTime = Carbon::createFromFormat('H:i:s', $checkInTime->format('H:i:s'));

                    if ($currentTime->gt($standardStart)) {
                        $diff = round(abs($currentTime->diffInMinutes($standardStart)));
                        if ($diff > 5) { // Ân hạn 5 phút
                            $totalLateMinutes += $diff;
                        }
                    }
                }
            }
            $lateDeduction = $totalLateMinutes * $finePerMinute;

            // --- PHẦN 3: TÍNH BẢO HIỂM ---
            $bhxh = $baseSalary * 0.08;
            $bhyt = $baseSalary * 0.015;
            $bhtn = $baseSalary * 0.01;
            $totalInsurance = $bhxh + $bhyt + $bhtn;

            // --- PHẦN 4: THỰC NHẬN ---
            $netSalary = $salaryByDays - $totalInsurance - $lateDeduction;

            // Kiểm tra xem đã chốt lương chưa
            $existPayslip = Payslip::where('user_id', $user->id)
                ->where('month', $month)
                ->where('year', $year)
                ->first();

            return [
                'user_id'            => $user->id,
                'name'               => $user->name,
                'salary_level'       => $user->salaryLevel->level_name ?? 'N/A',
                'base_salary'        => $baseSalary,
                'total_work_days'    => $actualWorkDays,
                'paid_leave_days'    => $paidLeaveDays,
                'total_payable_days' => $totalPayableDays,
                'total_late_minutes' => (int)$totalLateMinutes,
                'late_deduction'     => round($lateDeduction),
                'total_insurance'    => round($totalInsurance),
                'net_salary'         => round($netSalary > 0 ? $netSalary : 0),
                'status'             => $existPayslip ? $existPayslip->status : 'draft',
            ];
        });

        return response()->json($data);
    }

public function storePayslip(Request $request)
    {
        // Validate dữ liệu đầu vào (Nên có để tránh lỗi)
        $request->validate([
            'user_id' => 'required',
            'month' => 'required',
            'year' => 'required',
        ]);

        $data = [
            'user_id'            => $request->user_id,
            'month'              => $request->month,
            'year'               => $request->year,
            'total_work_days'    => $request->total_work_days,
            'paid_leave_days'    => $request->paid_leave_days,
            'total_payable_days' => $request->total_payable_days,
            'total_late_minutes' => $request->total_late_minutes,
            'late_deduction'     => $request->late_deduction,
            
            // --- SỬA LẠI CÁC DÒNG DƯỚI ĐÂY ---
            'bonus'              => $request->bonus ?? 0,          // Thêm dòng này
            'deduction'          => $request->deduction ?? 0,      // Thêm dòng này
           
            'insurance_amount'   => $request->insurance_amount, // Đã sửa (cũ là total_insurance)
            'final_salary'       => $request->final_salary,       // Sửa tên khớp với Frontend
            'status'             => 'paid',                        // Mặc định là paid khi chốt
        ];

        $payslip = Payslip::updateOrCreate(
            [
                'user_id' => $data['user_id'],
                'month'   => $data['month'],
                'year'    => $data['year'],
            ],
            $data
        );

        return response()->json([
            'message' => 'Đã chốt lương thành công!',
            'data' => $payslip
        ]);
    }
    public function getMyPayslips(Request $request)
    {
        $userId = auth()->id();

        // Dùng with(['user']) để lấy tên nhân viên từ bảng users
        $payslips = Payslip::with(['user'])
            ->where('user_id', $userId)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get();

        return response()->json($payslips);
    }

    public function exportPDF($id)
    {
        // Lấy dữ liệu phiếu lương kèm thông tin User và Bậc lương
        $payslip = Payslip::with(['user.salaryLevel'])->findOrFail($id);

        // Truyền dữ liệu vào file giao diện PDF
        $pdf = Pdf::loadView('pdf.payslip_template', compact('payslip'));

        // Tên file tải về (ví dụ: Phieu_Luong_Tram_Anh_T12.pdf)
        $fileName = 'Phieu_Luong_' . str_replace(' ', '_', $payslip->user->name) . '_T' . $payslip->month . '.pdf';

        return $pdf->download($fileName);
    }
}
