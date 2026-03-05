<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    // 1. Lấy danh sách
    public function index()
    {
        $departments = Department::with('manager:id,name')
            ->orderBy('id', 'desc')
            ->get();

        // Map dữ liệu để Frontend dễ dùng
        $data = $departments->map(function ($dept) {
            return [
                'id' => $dept->id,
                'name' => $dept->name,
                'description' => $dept->description,
                'manager_name' => $dept->manager ? $dept->manager->name : 'Chưa bổ nhiệm',
                // Nếu bạn chưa làm bảng user có department_id thì tạm thời để 0
                'count' => 0 
            ];
        });

        return response()->json($data);
    }

    // 2. Thêm mới
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $dept = Department::create([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json(['message' => 'Tạo phòng ban thành công!', 'department' => $dept], 201);
    }

    // 3. Cập nhật (Sửa) - MỚI THÊM
    public function update(Request $request, $id)
    {
        $dept = Department::find($id);
        if (!$dept) return response()->json(['message' => 'Không tìm thấy'], 404);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        $dept->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        return response()->json(['message' => 'Cập nhật thành công!', 'department' => $dept]);
    }

    // 4. Xóa
    public function destroy($id)
    {
        $dept = Department::find($id);
        if ($dept) {
            $dept->delete();
            return response()->json(['message' => 'Đã xóa phòng ban thành công.']);
        }
        return response()->json(['message' => 'Không tìm thấy phòng ban.'], 404);
    }
}