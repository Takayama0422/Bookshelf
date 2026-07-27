<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

class DatabaseNotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * 通知のMorph型と所有者IDがユーザーに一致する場合のみ更新を許可する。
     *
     * @param  User  $user  更新を試みるユーザー
     * @param  DatabaseNotification  $notification  更新対象のデータベース通知
     * @return bool 通知を更新できる場合はtrue
     */
    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (int) $notification->notifiable_id === $user->id;
    }
}
