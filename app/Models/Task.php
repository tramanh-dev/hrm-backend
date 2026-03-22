<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'assigned_to_user_id',
        'created_by_user_id',
        'due_date',
        'status',
        'is_pinned',
        'report_content',
        'report_file_path',
        'attachment_path',
    ];
  protected $casts = [
    'attachment_path' => 'array', 
];
    // Quan hệ với người tạo task
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Quan hệ cũ (1 người) 
    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    // Quan hệ mới (Nhiều người) 
    public function assignees()
    {
        return $this->belongsToMany(User::class, 'task_assignees', 'task_id', 'user_id');
    }
}
