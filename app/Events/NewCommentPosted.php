<?php

namespace App\Events;

use App\Models\TaskComment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; // QUAN TRỌNG: Phải có dòng này
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewCommentPosted implements ShouldBroadcast // Thêm implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $comment;

    public function __construct(TaskComment $comment)
    {
        // Load thông tin user để bên React có tên người gửi luôn
        $this->comment = $comment->load('user:id,name,avatar');
    }

    public function broadcastOn()
    {
        // Tạo một kênh riêng cho mỗi Task để tin nhắn không bị nhảy sang Task khác
        return new Channel('task.chat.' . $this->comment->task_id);
    }

    public function broadcastAs()
    {
        return 'NewComment'; // Tên sự kiện để React lắng nghe
    }
}
