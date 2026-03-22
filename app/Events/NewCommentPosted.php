<?php

namespace App\Events;

use App\Models\TaskComment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast; 
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewCommentPosted implements ShouldBroadcast 
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $comment;

    public function __construct(TaskComment $comment)
    {
        $this->comment = $comment->load('user:id,name,avatar');
    }

    public function broadcastOn()
    {
        return new Channel('task.chat.' . $this->comment->task_id);
    }

    public function broadcastAs()
    {
        return 'NewComment'; 
    }
}
