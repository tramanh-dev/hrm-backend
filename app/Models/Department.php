<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'manager_id'];

    // Quan hệ: Một phòng ban có 1 trưởng phòng (User)
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    // Quan hệ: Một phòng ban có nhiều nhân viên
    // public function employees()
    // {
    //     return $this->hasMany(User::class, 'department_id');
    // }

       public function users()
    {
        return $this->hasMany(User::class, 'department_id');
    }
   
}