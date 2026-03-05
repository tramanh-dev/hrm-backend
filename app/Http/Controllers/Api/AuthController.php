<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();


        if (! $user) {
            return response()->json([
                'status' => 401,
                'message' => 'Lỗi: Không tìm thấy Email ' . $request->email . ' trong Database!'
            ], 401);
        }
        if (! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 401,
                'message' => 'Lỗi: Tìm thấy Email, nhưng Mật khẩu không khớp!'
            ], 401);
        }

        /** @var \App\Models\User $user */
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 200,
            'message' => 'Đăng nhập thành công',
            'access_token' => $token,
            'user' => $user
        ]);
    }

    public function logout()
    {

        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user) {

            $user->tokens()->delete();
        }

        return response()->json([
            'status' => 200,
            'message' => 'Đăng xuất thành công'
        ]);
    }
}
