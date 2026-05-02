<?php

namespace Fluxio\Identity\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function attempt(string $email, string $password): ?array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        $token = $user->createToken('api')->plainTextToken;

        return compact('user', 'token');
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }
}
