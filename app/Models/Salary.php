<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Salary extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'salary_level', 'base_salary', 'allowance_position', 'allowance_phone', 'allowance_meal'];
}
