<?php

namespace App\Http\Controllers\Api;

use Illuminate\Support\Facades\DB;
use App\Events\TaskUpdated;
use Illuminate\Http\Request;
use App\Models\Task;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\TaskComment;

class TaskController extends Controller
{

    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['error' => 'Chưa đăng nhập'], 401);
        $userId = $user->id;

        // --- SỬA LẠI LOGIC INDEX ĐỂ GỌN HƠN ---
        $tasks = Task::where(function ($query) use ($userId) {
            $query->where('assigned_to_user_id', $userId) // Trực tiếp
                ->orWhere('created_by_user_id', $userId) // Người tạo
                ->orWhereHas('assignees', function ($q) use ($userId) { // Gián tiếp (qua bảng phụ)
                    $q->where('users.id', $userId);
                });
        })
            ->with('assignees')
            ->orderBy('created_at', 'desc')
            ->get();

        // SỬA LỖI: Trả về biến $tasks đã lọc, KHÔNG dùng Task::all()
        return response()->json($tasks);
    }

    public function getTasksByEmployee($id)
    {
        // Tìm nhân viên theo ID được gửi lên (ví dụ: ID của nhân viên 2)
        $user = \App\Models\User::find($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // 1. Lấy task được giao trực tiếp (Cột assigned_to_user_id)
        $directTasks = Task::where('assigned_to_user_id', $id)->get();
        // Đánh dấu để Frontend biết đây là task chính
        foreach ($directTasks as $task) {
            $task->role_type = 'Direct';
        }

        // 2. Lấy task gián tiếp (Nằm trong bảng phụ task_assignees)
        // whereHas: Lọc những task CÓ quan hệ assignees mà trong đó có user id này
        $indirectTasks = Task::whereHas('assignees', function ($q) use ($id) {
            $q->where('users.id', $id);
        })->get();

        // Đánh dấu để Frontend biết đây là task phụ
        foreach ($indirectTasks as $task) {
            $task->role_type = 'Indirect';
        }

        // 3. Gộp 2 danh sách lại và loại bỏ trùng lặp (nếu có)
        $allTasks = $directTasks->merge($indirectTasks)->unique('id')->values();

        return response()->json($allTasks);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $path = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('task_attachments', $fileName, 'public');
        }

        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'assigned_to_user_id' => $request->assigned_to_user_id,
            'created_by_user_id' => $user->id,
            'due_date' => $request->due_date,
            'status' => $request->assigned_to_user_id ? 1 : 0,
            'attachment_path' => $path ? [$path] : [],
        ]);

        if ($request->assigned_to_user_id) {
            $task->assignees()->attach($request->assigned_to_user_id);
        }

        $task->load('assignees');

        broadcast(new \App\Events\TaskUpdated($task, 'create'))->toOthers();

        return response()->json($task, 201);
    }

    // 2. HÀM PHÂN CÔNG 
    public function assignUsers(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $files = is_array($task->attachment_path) ? $task->attachment_path : [];
        if ($request->has('delete_files')) {
            foreach ($request->delete_files as $pathToDelete) {
                \Storage::disk('public')->delete($pathToDelete);
                $files = array_filter($files, fn($f) => $f !== $pathToDelete);
            }
        }

        // Xử lý THÊM file mới (Gộp vào mảng cũ thành task 2-3 file)
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $fileName = time() . '_' . $file->getClientOriginalName();
            $newPath = $file->storeAs('task_attachments', $fileName, 'public');
            $files[] = $newPath;
        }

        $task->update([
            'attachment_path' => array_values($files),
            'due_date' => $request->due_date ?? $task->due_date
        ]);

        if ($request->has('user_ids')) {
            $task->assignees()->sync($request->user_ids);
        }

        $task->load('assignees');
        // GIỮ WEBSOCKET CHO BÀ:
        broadcast(new \App\Events\TaskUpdated($task, 'update'))->toOthers();

        return response()->json($task, 200);
    }
    public function togglePin($id)
    {
        $task = Task::find($id);
        if ($task) {
            $task->is_pinned = !$task->is_pinned;
            $task->save();
            return response()->json(['message' => 'Đã cập nhật ghim', 'is_pinned' => $task->is_pinned]);
        }
        return response()->json(['message' => 'Không tìm thấy task'], 404);
    }

    public function myTasks(Request $request)
    {
        $userId = $request->user()->id;

        // Logic lấy "Việc của tôi" (bao gồm cả việc được giao nhóm)
        $tasks = Task::where(function ($query) use ($userId) {
            // 1. Việc giao trực tiếp (Kiểu cũ)
            $query->where('assigned_to_user_id', $userId)

                // 2. [QUAN TRỌNG] Việc giao qua nhóm (Bảng phụ)
                ->orWhereHas('assignees', function ($q) use ($userId) {
                    $q->where('users.id', $userId);
                });
        })
            ->with('assignees') // Load kèm danh sách người làm cùng
            ->orderBy('due_date', 'asc') // Sắp xếp theo hạn chót
            ->get();

        return response()->json($tasks);
    }

    // --- HÀM LẤY CHAT ---
    public function getComments($id)
    {
        $comments = TaskComment::where('task_id', $id)
            ->with('user:id,name,avatar')
            ->get();

        return response()->json($comments);
    }

    // --- HÀM GỬI CHAT ---
    public function postComment(Request $request, $id)
    {
        $comment = TaskComment::create([
            'task_id' => $id,
            'user_id' => auth()->id(),
            'content' => $request->content
        ]);

        $data = $comment->load('user:id,name,avatar');


        broadcast(new \App\Events\NewCommentPosted($data))->toOthers();

        return response()->json($data);
    }
}
