<?php

namespace App\Http\Controllers\Api;

use App\Events\LeaveStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Leave;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LeaveController extends Controller
{

    // Gửi đơn xin nghỉ 
    public function store(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
            'reason'     => 'required|string',
            'leave_type' => 'required|string', 
        ]);

        // Tính toán số ngày nghỉ bằng Carbon
        $start = \Carbon\Carbon::parse($request->start_date);
        $end = \Carbon\Carbon::parse($request->end_date);

        // Công thức tính số ngày nghỉ: $Days = (End - Start) + 1$
        $duration = $start->diffInDays($end) + 1;

        $leave = Leave::create([
            'user_id'    => auth()->id(),
            'start_date' => $request->start_date,
            'end_date'   => $request->end_date,
            'reason'     => $request->reason,
            'leave_type' => $request->leave_type,
            'duration_days' => $duration, 
            'status'     => 'pending'
        ]);

        return response()->json(['message' => 'Gửi đơn thành công!', 'leave' => $leave], 201);
    }
    // Xem lịch sử nghỉ phép của cá nhân 
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Lấy tất cả đơn của người dùng hiện tại
        $leaves = Leave::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($leaves);
    }


    // Xem TẤT CẢ đơn cho HR 
    public function allLeaves()
    {

        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!$user || !$user->isHR()) {
            return response()->json(['message' => 'Bạn không có quyền thực hiện hành động này.'], 403);
        }

        // Lấy tất cả đơn, kèm theo tên nhân viên (Eager Load user)
        $leaves = Leave::with('user:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($leaves);
    }

    public function updateStatus(Request $request, $id)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Chỉ HR mới có quyền duyệt đơn
        if (!$user || !$user->isHR()) {
            return response()->json(['message' => 'Bạn không có quyền thực hiện hành động này.'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
            'admin_comment' => 'nullable|string'
        ]);

        $leave = Leave::find($id);
        if (!$leave) return response()->json(['message' => 'Không tìm thấy đơn nghỉ phép'], 404);

        $leave->update([
            'status' => $request->status,
            'admin_comment' => $request->admin_comment
        ]);
        event(new LeaveStatusUpdated($leave));
        return response()->json(['message' => 'Cập nhật trạng thái thành công!', 'leave' => $leave]);
    }
}
