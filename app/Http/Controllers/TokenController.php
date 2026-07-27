<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueTokenRequest;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;

class TokenController extends Controller
{
    public function store(IssueTokenRequest $request, ApiTokenService $tokens): JsonResponse
    {
        $token = $tokens->issue(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
            $request->string('token_name')->toString(),
        );

        if (! $token) {
            return response()->json([
                'message' => '認証情報が正しくありません。',
                'errors' => [
                    'email' => ['認証情報が正しくありません。'],
                ],
            ], 401);
        }

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $token,
        ]);
    }
}
