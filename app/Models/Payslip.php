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
        'total_work_days',     
        'paid_leave_days',     
        'total_payable_days',  
        'bonus',
        'deduction',          
        'insurance_amount',
        'total_late_minutes',  
        'late_deduction',     
        'final_salary',        
        'status',
    ];

    // Một phiếu lương thuộc về một nhân viên
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
