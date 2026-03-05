<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Task; // 👈 QUAN TRỌNG: Import Model Task

/**
 * @method \Laravel\Sanctum\NewAccessToken createToken(string $name, array $abilities = [])
 */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasFactory;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar',
        'phone_number',
        'address',
        'date_of_birth',
        'department_id',
        'salary_level_id',
        'face_data',
    ];
    protected $hidden = [
        'password',
        'remember_token',
    ];
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    public function isHR(): bool
    {
        return $this->role === 'HR';
    }

    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }

    // --- MỐI QUAN HỆ VỚI BẢNG CÔNG VIỆC (Task) ---
    public function tasksCreated(): HasMany
    {
        return $this->hasMany(Task::class, 'created_by_user_id');
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class, 'task_assignees', 'user_id', 'task_id');
    }

    // 1. Quan hệ các task mà user này làm người chịu trách nhiệm chính (Trực tiếp)
    public function assignedTasks()
    {
        return $this->hasMany(Task::class, 'assigned_to_user_id');
    }

    // 2. Quan hệ các task mà user này tham gia (Gián tiếp - từ bảng phụ task_assignees)
    public function participatedTasks()
    {
        return $this->belongsToMany(Task::class, 'task_assignees', 'user_id', 'task_id');
    }
    public function salary()
    {
        return $this->hasOne(Salary::class);
    }
    public function salaryLevel()
    {
        return $this->belongsTo(\App\Models\SalaryLevel::class, 'salary_level_id');
    }
    public function payslips()
    {
        return $this->hasMany(Payslip::class);
    }
    public function timesheets()
    {
        return $this->hasMany(Timesheet::class);
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class);
    }
}
