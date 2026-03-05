<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User; 

class TaskComment extends Model
{
    use HasFactory;

    protected $fillable = ['task_id', 'user_id', 'content'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}