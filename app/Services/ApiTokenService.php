<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ApiTokenService
{
    public function issue(string $email, string $password, string $tokenName): ?string
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user->createToken($tokenName)->plainTextToken;
    }
}
