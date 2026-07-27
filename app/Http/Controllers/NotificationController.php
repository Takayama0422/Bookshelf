<?php

namespace App\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * 一覧表示を許可されたユーザー自身の通知を新しい順で取得する。
     *
     * @param  Request  $request  認証済みユーザーを保持するリクエスト
     * @return View ページネーション済み通知を含む一覧画面
     *
     * @throws AuthorizationException 通知一覧の表示が許可されていない場合
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', DatabaseNotification::class);

        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(10);

        return view('notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    /**
     * 所有者として更新を許可された通知を既読にする。
     *
     * 未読の場合のみ既読日時をデータベースへ保存する。
     *
     * @param  DatabaseNotification  $notification  既読化するデータベース通知
     * @return RedirectResponse 通知一覧へのリダイレクトレスポンス
     *
     * @throws AuthorizationException 通知の更新が許可されていない場合
     */
    public function read(DatabaseNotification $notification): RedirectResponse
    {
        $this->authorize('update', $notification);

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        return redirect()
            ->route('notifications.index')
            ->with('success', '通知を既読にしました。');
    }
}
