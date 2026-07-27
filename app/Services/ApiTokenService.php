<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ApiTokenService
{
    /**
     * メールアドレスとパスワードを検証し、指定名のSanctumトークンを発行する。
     *
     * 認証成功時はトークンをデータベースへ保存し、平文トークンを一度だけ返す。
     *
     * @param  string  $email  認証するメールアドレス
     * @param  string  $password  検証する平文パスワード
     * @param  string  $tokenName  発行するトークンの名前
     * @return string|null 発行した平文トークン。認証失敗時はnull
     */
    public function issue(string $email, string $password, string $tokenName): ?string
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user->createToken($tokenName)->plainTextToken;
    }
}
