<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public const DEMO_EMAIL = 'demo@loqfy.test';

    public function demo(Request $request): array
    {
        $user = User::firstOrCreate(
            ['email' => self::DEMO_EMAIL],
            ['name' => 'Demonstração', 'password' => Str::random(40)],
        );

        Auth::login($user);
        $request->session()->regenerate();

        return $this->present($user);
    }

    public function me(Request $request): array
    {
        return $this->present($request->user());
    }

    public function logout(Request $request): array
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ['signed_out' => true];
    }

    private function present(User $user): array
    {
        return ['name' => $user->name, 'email' => $user->email];
    }
}
