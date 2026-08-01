<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** 용병단 창설 (name = 용병단 이름) */
    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $this->present($user)], 201);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['이메일 또는 비밀번호가 일치하지 않습니다.'],
            ]);
        }

        $request->session()->regenerate();

        return response()->json(['user' => $this->present(Auth::user())]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->present($request->user())]);
    }

    private function present(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'level' => $user->level,
            'exp' => $user->exp,
            // 레벨업 공식이 아직 기획되지 않아 임시로 level*1000을 씀 - 밸런스
            // 확정되면 교체.
            'exp_to_next' => $user->level * 1000,
            'gold' => $user->gold,
            'honor_points' => $user->honor_points,
            'fatigue' => $user->fatigue,
            'max_fatigue' => $user->max_fatigue,
            'icon_path' => $user->icon_path,
        ];
    }
}
