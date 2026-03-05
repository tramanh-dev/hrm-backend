<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timesheet extends Model
{
    use HasFactory;

    // Khai báo các cột được phép thêm dữ liệu
    protected $fillable = [
        'user_id',
        'work_date',
        'check_in',
        'check_out',
        'day_count',
        'lat',
        'lng',
    ];

    // Quan hệ: Một dòng chấm công thuộc về một nhân viên (User)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
