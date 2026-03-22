<?php

namespace App\Events;

use App\Models\Task;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $task;
    public $action;

    public function __construct(Task $task, $action = 'update')
    {
        $this->task = $task->load('assignees');

        $this->action = $action;
    }
    public function broadcastOn()
    {
        return new Channel('task-board');
    }
    public function broadcastAs()
    {
        return 'TaskUpdated';
    }
}
