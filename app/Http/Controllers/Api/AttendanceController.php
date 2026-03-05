<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CompanySetting;
use App\Models\Timesheet;
use App\Models\User;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    // --- 1. ĐĂNG KÝ KHUÔN MẶT ---
    public function registerFace(Request $request)
    {
        $request->validate(['face_data' => 'required|array']);
        $user = auth()->user();
        $user->face_data = json_encode($request->face_data);
        $user->save();

        return response()->json(['message' => 'Đăng ký khuôn mặt thành công!']);
    }

    // --- 2. KIỂM TRA VỊ TRÍ GPS ---
    public function verifyLocation(Request $request)
    {
        try {
            $request->validate([
                'latitude' => 'required|numeric',
                'longitude' => 'required|numeric',
            ]);

            $company = CompanySetting::first();
            if (!$company) {
                return response()->json(['message' => 'Chưa cấu hình tọa độ công ty!'], 404);
            }

            // Gọi hàm tính khoảng cách nằm ở bên dưới
            $distance = $this->calculateDistance(
                $request->latitude,
                $request->longitude,
                $company->latitude,
                $company->longitude
            );

            if ($distance > $company->radius) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Bạn đang ở ngoài phạm vi công ty (' . round($distance) . 'm)',
                ], 403);
            }

            return response()->json(['status' => 'success', 'message' => 'Vị trí hợp lệ']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    // --- 3. CHẤM CÔNG VÀO (CHECK-IN) ---
    public function checkIn(Request $request)
    {
        try {
            $user = auth()->user();
            $today = Carbon::today()->toDateString();

            $alreadyIn = Timesheet::where('user_id', $user->id)->where('work_date', $today)->first();
            if ($alreadyIn) {
                return response()->json(['message' => 'Hôm nay bạn đã chấm công rồi!'], 400);
            }

            $inputDescriptor = $request->face_descriptors;
            $storedDescriptor = json_decode($user->face_data);

            if (!$storedDescriptor) {
                return response()->json(['message' => 'Bạn chưa đăng ký khuôn mặt!'], 400);
            }

            // Gọi hàm so sánh mặt nằm ở bên dưới
            $distance = $this->euclideanDistance($inputDescriptor, $storedDescriptor);

            if ($distance < 0.4) {
                $newRecord = Timesheet::create([
                    'user_id'   => $user->id,
                    'work_date' => $today,
                    'check_in'  => Carbon::now()->toTimeString(),
                    'lat'       => $request->lat,
                    'lng'       => $request->lng,
                ]);

                return response()->json(['message' => 'Chấm công thành công!', 'time' => $newRecord->check_in]);
            }

            return response()->json(['message' => 'Khuôn mặt không khớp!'], 401);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi hệ thống: ' . $e->getMessage()], 500);
        }
    }

    // --- 4. CHẤM CÔNG RA (CHECK-OUT) ---
    public function checkOut(Request $request)
    {
        try {
            $user = auth()->user();
            $today = Carbon::today()->toDateString();
            $record = Timesheet::where('user_id', $user->id)->where('work_date', $today)->first();

            if (!$record) return response()->json(['message' => 'Bạn chưa check-in!'], 400);
            if ($record->check_out) return response()->json(['message' => 'Bạn đã check-out rồi!'], 400);

            $checkInTime = Carbon::parse($record->check_in);
            $checkOutTime = Carbon::now();
            $hoursWorked = $checkOutTime->diffInMinutes($checkInTime) / 60;

            $count = ($hoursWorked >= 7) ? 1.0 : (($hoursWorked >= 3.5) ? 0.5 : 0);

            $record->update([
                'check_out' => $checkOutTime->toTimeString(),
                'day_count' => $count
            ]);

            return response()->json(['message' => "Check-out thành công! Nhận được $count công."]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    // Hàm tính khoảng cách GPS
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($lat1);
        $lonFrom = deg2rad($lon1);
        $latTo = deg2rad($lat2);
        $lonTo = deg2rad($lon2);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
            cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }

    // Hàm tính độ lệch khuôn mặt
    private function euclideanDistance($arr1, $arr2)
    {
        if (!$arr1 || !$arr2 || count($arr1) !== count($arr2)) return 999;
        $sum = 0;
        for ($i = 0; $i < count($arr1); $i++) {
            $sum += pow($arr1[$i] - $arr2[$i], 2);
        }
        return sqrt($sum);
    }
}
