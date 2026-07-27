<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(10);

        return view('notifications.index', [
            'notifications' => $notifications,
        ]);
    }

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
