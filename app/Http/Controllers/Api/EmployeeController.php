<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    // --- 1. LẤY DANH SÁCH (CHO HR) ---
    public function index()
    {
        $currentUser = Auth::user();
        if (!$currentUser->isHR()) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $employees = User::query()
            ->with('department')
            ->withCount(['tasks', 'assignedTasks'])
            ->orderBy('id', 'desc')
            ->get();

        // Cộng tổng task
        $employees->each(function ($emp) {
            $emp->tasks_count = $emp->tasks_count + $emp->assigned_tasks_count;
        });

        return response()->json($employees);
    }

    // --- 2. TẠO MỚI (CHO HR) ---
    public function store(Request $request)
    {
        $currentUser = Auth::user();
        if (!$currentUser->isHR()) return response()->json(['message' => 'Forbidden'], 403);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:6',
            'role' => ['nullable', Rule::in(['Employee', 'HR'])],
            'department_id' => 'nullable|exists:departments,id',
            'salary_level_id' => 'required|exists:salary_levels,id',
        ]);

        $employee = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'Employee',
            'department_id' => $request->department_id,
            'salary_level_id' => $request->salary_level_id,
        ]);

        return response()->json(['message' => 'Tạo thành công!', 'employee' => $employee], 201);
    }

    // --- 3. XEM CHI TIẾT (LẤY CẢ HR VÀ NHÂN VIÊN) ---
    public function show($id)
    {
        $currentUser = Auth::user();

        // Nếu bạn muốn CHỈ HR mới được xem người khác, 
        // còn Employee chỉ được xem chính mình thì giữ nguyên:
        if (!$currentUser->isHR() && $currentUser->id != $id) {
            return response()->json(['message' => 'Bạn không có quyền xem thông tin này'], 403);
        }

        // Lấy thông tin user kèm theo phòng ban và có thể là danh sách task 
        // để biết HR đó đang quản lý gì hoặc Employee đó đang làm gì.
        $employee = User::with(['department'])
            ->withCount(['tasks', 'assignedTasks'])
            ->find($id);

        if (!$employee) {
            return response()->json(['message' => 'Không tìm thấy người dùng này'], 404);
        }

        // Trả về thêm thông tin role để frontend biết đây là HR hay Employee
        return response()->json($employee);
    }

    // --- 4. HR CẬP NHẬT NHÂN VIÊN (PUT /api/employees/{id}) ---
    public function update(Request $request, $id)
    {
        $currentUser = Auth::user();
        if (!$currentUser->isHR()) return response()->json(['message' => 'Chỉ HR mới được dùng chức năng này'], 403);

        $employee = User::find($id);
        if (!$employee) return response()->json(['message' => 'Không tìm thấy'], 404);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'role' => ['nullable', Rule::in(['Employee', 'HR'])],
            'department_id' => 'nullable|exists:departments,id',
            'phone_number' => 'nullable|string',
            'address' => 'nullable|string',
            'date_of_birth' => 'nullable|date',
        ]);

        $data = $request->except(['password']); // Lấy hết trừ password

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $employee->update($data);

        return response()->json(['message' => 'Cập nhật thành công!', 'employee' => $employee]);
    }

    // --- 5. NHÂN VIÊN TỰ CẬP NHẬT PROFILE (PUT /api/profile) ---
    public function updateProfile(Request $request)
    {
        $user = Auth::user(); // Lấy chính user đang login

        $request->validate([
            'name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'password' => 'nullable|min:6', // Không bắt buộc nhập pass
        ]);

        // Chỉ lấy các trường cho phép tự sửa
        $data = $request->only(['name', 'phone_number', 'address', 'date_of_birth']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Tuyệt đối không update 'role' hay 'department_id' ở đây
        $user->update($data);

        return response()->json([
            'message' => 'Cập nhật hồ sơ cá nhân thành công!',
            'user' => $user->load('department')
        ]);
    }

    // --- 6. UPLOAD AVATAR ---
    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|max:2048']);
        $user = Auth::user();

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
            $user->save();
            return response()->json(['message' => 'Upload ảnh thành công!', 'avatar_url' => asset('storage/' . $path), 'user' => $user]);
        }
        return response()->json(['message' => 'Lỗi upload'], 400);
    }

    // --- 7. XÓA (CHO HR) ---
    public function destroy($id)
    {
        if (!Auth::user()->isHR()) return response()->json(['message' => 'Forbidden'], 403);
        User::destroy($id);
        return response()->json(['message' => 'Đã xóa thành công']);
    }
}
