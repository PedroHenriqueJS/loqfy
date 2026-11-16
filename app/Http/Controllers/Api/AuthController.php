<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public const DEMO_EMAIL = 'demo@loqfy.test';

    public function demo(Request $request): UserResource
    {
        $user = User::firstOrCreate(
            ['email' => self::DEMO_EMAIL],
            ['name' => 'Demonstração', 'password' => Str::random(40)],
        );

        Auth::login($user);
        $request->session()->regenerate();

        return new UserResource($user);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): array
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ['signed_out' => true];
    }
}
