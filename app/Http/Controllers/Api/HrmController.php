<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payslip;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HrmController extends Controller
{

    public function getEmployees()
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        // Kiểm tra quyền HR
        if (!$user || !$user->isHR()) {
            return response()->json(['message' => 'Bạn không có quyền xem danh sách.'], 403);
        }

        // Lấy danh sách nhân viên 
        $employees = User::where('role', '!=', 'HR')
            ->whereNotNull('role')
            ->select('id', 'name', 'email')
            ->get();

        return response()->json($employees, 200);
    }
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !$user->isHR()) {
            return response()->json(['message' => 'Bạn không có quyền.'], 403);
        }

        $request->validate([
            'title' => 'required|string|max:255',
            'assigned_to_user_id' => 'required|exists:users,id',
            'attachment' => 'nullable|file|max:5120', // Tối đa 5MB
        ]);

        // Xử lý file
        // Xử lý file đính kèm
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
            'status' => 1,
            'due_date' => $request->due_date,
            'attachment_path' => $path, // Lưu đường dẫn file
        ]);

        $task->assignees()->attach($request->assigned_to_user_id);

        return response()->json(['message' => 'Tạo thành công!', 'task' => $task->load('assignees')], 201);
    }
    public function hrTasks()
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user || !$user->isHR()) {
            return response()->json(['message' => 'Bạn không có quyền.'], 403);
        }

        $tasks = Task::where('created_by_user_id', $user->id)
            ->with('assignedTo:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }
    public function showTask($id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $task = Task::with(['creator:id,name', 'assignedTo:id,name'])->find($id);

        if (!$task) {
            return response()->json(['message' => 'Không tìm thấy công việc.'], 404);
        }

        if ($task->assigned_to_user_id !== $user->id && $task->created_by_user_id !== $user->id) {
            return response()->json(['message' => 'Bạn không có quyền xem công việc này.'], 403);
        }

        if ($task->report_file_path) {
            $task->report_file_url = Storage::url($task->report_file_path);
        }

        return response()->json($task, 200);
    }

    public function updateTask(Request $request, $id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $task = Task::find($id);

        if (!$task) {
            return response()->json(['message' => 'Không tìm thấy công việc.'], 404);
        }

        // Chỉ HR hoặc người được giao (Employee) mới được cập nhật
        if ($task->created_by_user_id !== $user->id && $task->assigned_to_user_id !== $user->id) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật công việc này.'], 403);
        }

        $data = $request->only(['title', 'description', 'due_date', 'assigned_to_user_id', 'status']);

        // HR có thể cập nhật mọi thứ, nhân viên thì không
        if ($user->isHR()) {
            $task->update($data);
            $message = 'HR đã cập nhật công việc thành công.';
        } elseif (isset($data['status'])) {
        
            $task->update($request->only('status'));
            $message = 'Cập nhật trạng thái công việc thành công.';
        } else {
            return response()->json(['message' => 'Bạn không có quyền chỉnh sửa chi tiết công việc.'], 403);
        }

        return response()->json(['message' => $message, 'task' => $task], 200);
    }

    public function destroyTask($id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $task = Task::find($id);

        if (!$task) {
            return response()->json(['message' => 'Không tìm thấy công việc.'], 404);
        }

        // Chỉ người tạo (HR) mới được xóa task
        if (!$user->isHR() || $task->created_by_user_id !== $user->id) {
            return response()->json(['message' => 'Bạn không có quyền xóa công việc.'], 403);
        }

        // Xóa file báo cáo cũ nếu có
        if ($task->report_file_path) {
            Storage::disk('public')->delete($task->report_file_path);
        }
        $task->delete();
        return response()->json(['message' => 'Xóa công việc thành công.'], 200);
    }

    // của nhân viên
    public function myTasks()
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $tasks = Task::where('assigned_to_user_id', $user->id)
            ->with('creator:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($tasks);
    }

    public function submitReport(Request $request, $id)
    {
        //  dd($request->file('file'));
        $user = Auth::user();
        $task = Task::find($id);

        if (!$task) return response()->json(['message' => 'Không tìm thấy công việc'], 404);

        $isDirectAssignee = ($task->assigned_to_user_id == $user->id);
        $isInAssigneeList = $task->assignees()->where('users.id', $user->id)->exists();

        if (!$isDirectAssignee && !$isInAssigneeList) {
            return response()->json(['message' => 'Không phải việc của bạn!'], 403);
        }
        // Validate: Cho phép file Excel, Word, PDF, Ảnh. Max 5MB
        $request->validate([
            'report_content' => 'required|string',

            'file' => 'nullable|file|max:40960',
        ]);
        $filePath = null;

        // Xử lý lưu file nếu có
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('reports', 'public');
        }

        // Cập nhật Database
        $task->update([
            'report_content' => $request->report_content,
            'report_file_path' => $filePath,
            'status' => 2
        ]);

        return response()->json(['message' => 'Báo cáo thành công!', 'task' => $task]);
    }

    public function getEmployeesSummary(Request $request)
    {
        $currentUser = $request->user(); // Hoặc Auth::user()
        if (!$currentUser || !$currentUser->isHR()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = User::where('role', '!=', 'HR');

        if ($request->has('search') && $request->search != '') {
            $keyword = $request->search;

            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                    ->orWhere('email', 'LIKE', "%{$keyword}%")
                    ->orWhere('id', 'LIKE', "%{$keyword}%"); // Tìm cả theo ID
            });
        }
        $employees = $query->withCount(['tasks', 'assignedTasks'])
            ->orderBy('id', 'desc') // Sắp xếp mới nhất lên đầu
            ->get();

        $employees->each(function ($emp) {
            $emp->completed_tasks = $emp->tasks_count + $emp->assigned_tasks_count;

        });

        return response()->json($employees);
    }

    ///dashbAdmin
    public function getStats()
    {
        try {
            $userCount = \App\Models\User::where('role', '!=', 'HR')->count();
            $pendingTasks = \App\Models\Task::where('status', 1)->count();

            $chartData = [];
            if (class_exists('\App\Models\Department')) {
                $departments = \App\Models\Department::withCount('users')->get();
                $chartData = $departments->map(fn($d) => ['name' => $d->name, 'nv' => $d->users_count]);
            }

            $salaryData = \App\Models\Payslip::selectRaw('month, SUM(final_salary) as total_salary')
                ->groupBy('month')->orderBy('month', 'asc')->get();

            $leaveStats = [
                ['name' => 'Chờ duyệt', 'value' => \App\Models\Leave::where('status', 'pending')->count()],
                ['name' => 'Đã duyệt', 'value' => \App\Models\Leave::where('status', 'approved')->count()],
                ['name' => 'Từ chối', 'value' => \App\Models\Leave::where('status', 'rejected')->count()],
            ];

            return response()->json([
                'user_count'    => $userCount,
                'dept_count'    => $chartData->count(),
                'pending_tasks' => $pendingTasks,
                'payslip_count' => \App\Models\Payslip::count(),
                'chart_data'    => $chartData,
                'salary_data'   => $salaryData, 
                'leave_stats'   => $leaveStats, 
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
