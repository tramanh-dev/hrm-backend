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

            // Lấy tên file gốc hoặc tạo tên mới kèm đuôi file gốc
            $fileName = time() . '_' . $file->getClientOriginalName();

            // Lưu vào thư mục task_attachments với tên file đầy đủ
            $path = $file->storeAs('task_attachments', $fileName, 'public');
        }

        // Lúc này $path sẽ là: "task_attachments/1734857..._tailieu.xlsx"

        // Tạo Task (Chỉ 1 lần duy nhất)
        $task = Task::create([
            'title' => $request->title,
            'description' => $request->description,
            'assigned_to_user_id' => $request->assigned_to_user_id,
            'created_by_user_id' => $user->id,
            'status' => 1,
            'due_date' => $request->due_date,
            'attachment_path' => $path, // Lưu đường dẫn file
        ]);

        // Đồng bộ bảng phụ
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

        // Lấy task do HR này tạo, kèm tên nhân viên được giao
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

        // Lấy task, kèm thông tin người giao và người nhận
        $task = Task::with(['creator:id,name', 'assignedTo:id,name'])->find($id);

        if (!$task) {
            return response()->json(['message' => 'Không tìm thấy công việc.'], 404);
        }

        // Kiểm tra quyền xem: Phải là người giao (HR) HOẶC người được giao (Employee)
        if ($task->assigned_to_user_id !== $user->id && $task->created_by_user_id !== $user->id) {
            return response()->json(['message' => 'Bạn không có quyền xem công việc này.'], 403);
        }

        // Tùy biến đường dẫn file (trả về URL công khai nếu có file báo cáo)
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

        // Chỉ người tạo (HR) hoặc người được giao (Employee) mới được cập nhật
        if ($task->created_by_user_id !== $user->id && $task->assigned_to_user_id !== $user->id) {
            return response()->json(['message' => 'Bạn không có quyền cập nhật công việc này.'], 403);
        }

        $data = $request->only(['title', 'description', 'due_date', 'assigned_to_user_id', 'status']);

        // HR có thể cập nhật mọi thứ, nhân viên thì không
        if ($user->isHR()) {
            $task->update($data);
            $message = 'HR đã cập nhật công việc thành công.';
        } elseif (isset($data['status'])) {
            // Nếu là nhân viên, chỉ cho phép cập nhật trạng thái (nếu muốn)
            // Lưu ý: Chức năng submitReport đã xử lý việc chuyển sang status 2
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

        // --- LOGIC KIỂM TRA MỚI (SỬA ĐOẠN NÀY) ---

        // 1. Kiểm tra cột chính (assigned_to_user_id)
        $isDirectAssignee = ($task->assigned_to_user_id == $user->id);

        // 2. Kiểm tra trong bảng phụ (task_assignees)
        // Hàm này check xem User ID hiện tại có tồn tại trong danh sách assignees của Task không
        $isInAssigneeList = $task->assignees()->where('users.id', $user->id)->exists();

        // 3. Nếu KHÔNG phải người được gán trực tiếp VÀ cũng KHÔNG có trong danh sách phụ
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
        // 1. Kiểm tra quyền HR (Giữ nguyên logic cũ của bạn)
        $currentUser = $request->user(); // Hoặc Auth::user()
        if (!$currentUser || !$currentUser->isHR()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // 2. Khởi tạo Query lấy User (trừ ông HR ra)
        $query = User::where('role', '!=', 'HR');

        // 👇👇👇 [ĐOẠN CẦN THÊM] LOGIC TÌM KIẾM 👇👇👇
        if ($request->has('search') && $request->search != '') {
            $keyword = $request->search;

            // Nhóm điều kiện lại: (ID trùng HOẶC Tên trùng HOẶC Email trùng)
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'LIKE', "%{$keyword}%")
                    ->orWhere('email', 'LIKE', "%{$keyword}%")
                    ->orWhere('id', 'LIKE', "%{$keyword}%"); // Tìm cả theo ID
            });
        }
        // 👆👆👆 HẾT ĐOẠN CẦN THÊM 👆👆👆

        // 3. Tiếp tục logic đếm task và lấy dữ liệu
        $employees = $query->withCount(['tasks', 'assignedTasks'])
            ->orderBy('id', 'desc') // Sắp xếp mới nhất lên đầu
            ->get();

        // 4. Cộng dồn task (Giữ nguyên logic cũ)
        $employees->each(function ($emp) {
            // Cộng task chính + task được giao (nếu logic bạn là vậy)
            $emp->completed_tasks = $emp->tasks_count + $emp->assigned_tasks_count;

            // Lưu ý: Ở Frontend bạn đang hiển thị biến 'completed_tasks' (màu xanh lá)
            // Nên ở đây mình gán vào biến completed_tasks cho khớp nhé.
        });

        return response()->json($employees);
    }

    ///dashbAdmin
    public function getStats()
    {
        try {
            // 1. Đếm cơ bản
            $userCount = \App\Models\User::where('role', '!=', 'HR')->count();
            $pendingTasks = \App\Models\Task::where('status', 1)->count();

            // 2. Thống kê phòng ban (Biểu đồ cột)
            $chartData = [];
            if (class_exists('\App\Models\Department')) {
                $departments = \App\Models\Department::withCount('users')->get();
                $chartData = $departments->map(fn($d) => ['name' => $d->name, 'nv' => $d->users_count]);
            }

            // 3. Thống kê quỹ lương (Biểu đồ đường)
            $salaryData = \App\Models\Payslip::selectRaw('month, SUM(final_salary) as total_salary')
                ->groupBy('month')->orderBy('month', 'asc')->get();

            // 4. Thống kê đơn nghỉ phép (Biểu đồ tròn)
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
                'salary_data'   => $salaryData, // THÊM DÒNG NÀY
                'leave_stats'   => $leaveStats, // THÊM DÒNG NÀY
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }
}
