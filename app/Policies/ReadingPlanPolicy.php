<?php

namespace App\Policies;

use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * 認証済みユーザーに読書計画一覧の表示を許可する。
     *
     * @param  User  $user  一覧表示を試みるユーザー
     * @return bool 常にtrue
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * 認証済みユーザーに読書計画の作成を許可する。
     *
     * @param  User  $user  作成を試みるユーザー
     * @return bool 常にtrue
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * 読書計画の所有者にのみ更新を許可する。
     *
     * @param  User  $user  更新を試みるユーザー
     * @param  ReadingPlan  $readingPlan  更新対象の読書計画
     * @return bool 更新を許可する場合はtrue
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }

    /**
     * 読書計画の所有者にのみ読了状態への変更を許可する。
     *
     * @param  User  $user  読了操作を試みるユーザー
     * @param  ReadingPlan  $readingPlan  読了対象の読書計画
     * @return bool 読了操作を許可する場合はtrue
     */
    public function complete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }

    /**
     * 読書計画の所有者にのみ削除を許可する。
     *
     * @param  User  $user  削除を試みるユーザー
     * @param  ReadingPlan  $readingPlan  削除対象の読書計画
     * @return bool 削除を許可する場合はtrue
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }
}
