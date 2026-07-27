<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueTokenRequest;
use App\Services\ApiTokenService;
use Illuminate\Http\JsonResponse;

class TokenController extends Controller
{
    /**
     * 認証情報を検証して新しいAPIトークンを発行する。
     *
     * 発行に成功した場合はBearerトークンを返し、認証失敗時はエラー情報を401で返す。
     * 成功時は指定された名前のアクセストークンがデータベースへ保存される。
     *
     * @param  IssueTokenRequest  $request  検証済みの認証情報とトークン名
     * @param  ApiTokenService  $tokens  APIトークン発行サービス
     * @return JsonResponse 発行済みトークンまたは認証エラーのJSONレスポンス
     */
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
