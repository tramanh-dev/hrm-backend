<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'month',
        'year',
        'total_work_days',     // Ngày đi làm thực tế
        'paid_leave_days',     // Ngày nghỉ phép có lương (Mới thêm)
        'total_payable_days',   // Tổng ngày tính lương (Mới thêm)
        'bonus',
        'deduction',           // Phạt vi phạm khác
        'insurance_amount',
        'total_late_minutes',  // Số phút đi trễ
        'late_deduction',      // Tiền phạt đi trễ
        'final_salary',        // Thực nhận (net_salary từ React gửi về)
        'status',
    ];

    // Thiết lập quan hệ: Một phiếu lương thuộc về một nhân viên
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
