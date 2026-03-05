<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalaryLevel extends Model
{
    use HasFactory;
    protected $fillable = ['level_name', 'base_salary', 'allowance'];

    // Một bậc lương có nhiều nhân viên
    public function users() {
        return $this->hasMany(User::class);
    }
}