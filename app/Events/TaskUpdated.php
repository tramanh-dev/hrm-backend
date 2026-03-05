<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcast // <--- Bắt buộc phải có cái này
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;
    public $action; // Để phân biệt là 'create' hay 'update'

    // Nhận vào Task object và hành động
    // Trong file App\Events\TaskUpdated.php

    public function __construct(Task $task, $action = 'update')
    {
        // THÊM ĐOẠN ->load('assignees') VÀO ĐÂY
        // Để Laravel biết phải gửi kèm danh sách người được giao
        $this->task = $task->load('assignees');

        $this->action = $action;
    }
    // Định nghĩa kênh phát sóng
    public function broadcastOn()
    {
        return new Channel('task-board');
    }
    public function broadcastAs()
{
    return 'TaskUpdated';
}
}
