<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\IndexReadingPlanRequest;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * 認可されたユーザーの読書計画を状態で絞り込み、一覧表示する。
     *
     * @param  IndexReadingPlanRequest  $request  検証済みの状態条件を保持するリクエスト
     * @return View 読書計画、絞り込み条件、状態一覧を含む一覧画面
     */
    public function index(IndexReadingPlanRequest $request): View
    {
        $plans = $request->user()
            ->readingPlans()
            ->with('book')
            ->when($request->status(), fn ($query, string $status) => $query->where('status', $status))
            ->latest()
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('reading-plans.index', [
            'plans' => $plans,
            'filters' => ['status' => $request->status()],
            'statuses' => ReadingPlan::statuses(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', ReadingPlan::class);

        return view('reading-plans.create', [
            'books' => $this->booksForForm(),
        ]);
    }

    /**
     * 認可済みユーザーの読書計画をトランザクション内で登録する。
     *
     * ユーザー行をロックして進行中計画の重複を再確認し、競合する登録を防止する。
     *
     * @param  StoreReadingPlanRequest  $request  検証済みの読書計画登録内容
     * @return RedirectResponse 読書計画一覧へのリダイレクトレスポンス
     *
     * @throws AuthorizationException 読書計画の作成が許可されていない場合
     * @throws ValidationException 同じ書籍の進行中計画が既に存在する場合
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $this->authorize('create', ReadingPlan::class);

        DB::transaction(function () use ($request): void {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            $this->ensureNoInProgressDuplicate(
                $request->user()->id,
                (int) $request->validated('book_id')
            );

            $request->user()->readingPlans()->create([
                'book_id' => $request->validated('book_id'),
                'target_date' => $request->validated('target_date'),
                'status' => ReadingPlanStatus::IN_PROGRESS,
            ]);
        });

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を登録しました。');
    }

    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);

        return view('reading-plans.edit', [
            'readingPlan' => $readingPlan,
            'books' => $this->booksForForm(),
        ]);
    }

    /**
     * 所有者として認可された読書計画をトランザクション内で更新する。
     *
     * ユーザー行をロックし、対象が進行中の場合は自身を除外して重複を再確認する。
     *
     * @param  UpdateReadingPlanRequest  $request  検証済みの読書計画更新内容
     * @param  ReadingPlan  $readingPlan  更新対象の読書計画
     * @return RedirectResponse 読書計画一覧へのリダイレクトレスポンス
     *
     * @throws AuthorizationException 読書計画の更新が許可されていない場合
     * @throws ValidationException 同じ書籍の別の進行中計画が存在する場合
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        DB::transaction(function () use ($request, $readingPlan): void {
            User::query()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();

            if ($readingPlan->status === ReadingPlanStatus::IN_PROGRESS) {
                $this->ensureNoInProgressDuplicate(
                    $request->user()->id,
                    (int) $request->validated('book_id'),
                    $readingPlan->id
                );
            }

            $readingPlan->update($request->readingPlanAttributes());
        });

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * 所有者として認可された読書計画をトランザクション内で読了状態へ遷移させる。
     *
     * 未読了の場合のみ状態と読了日時を更新し、既に読了済みの場合はDB更新を行わない。
     *
     * @param  ReadingPlan  $readingPlan  読了状態へ遷移させる読書計画
     * @return RedirectResponse 読書計画一覧へのリダイレクトレスポンス
     *
     * @throws AuthorizationException 読書計画の読了操作が許可されていない場合
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('complete', $readingPlan);

        DB::transaction(function () use ($readingPlan): void {
            if ($readingPlan->status !== ReadingPlanStatus::COMPLETED) {
                $readingPlan->update([
                    'status' => ReadingPlanStatus::COMPLETED,
                    'completed_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を読了にしました。');
    }

    /**
     * 所有者として削除を許可された読書計画をトランザクション内で削除する。
     *
     * @param  ReadingPlan  $readingPlan  削除対象の読書計画
     * @return RedirectResponse 読書計画一覧へのリダイレクトレスポンス
     *
     * @throws AuthorizationException 読書計画の削除が許可されていない場合
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        DB::transaction(fn (): bool => $readingPlan->delete());

        return redirect()
            ->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    private function booksForForm(): Collection
    {
        return Book::query()->orderBy('title')->orderBy('id')->get(['id', 'title']);
    }

    /**
     * 指定ユーザーと書籍の組み合わせに進行中の読書計画が重複していないことを確認する。
     * 戻り値はなく、重複時はバリデーション例外を送出する。
     *
     * @param  int  $userId  確認対象のユーザーID
     * @param  int  $bookId  確認対象の書籍ID
     * @param  int|null  $ignorePlanId  重複検査から除外する読書計画ID
     *
     * @throws ValidationException 進行中の読書計画が既に存在する場合
     */
    private function ensureNoInProgressDuplicate(int $userId, int $bookId, ?int $ignorePlanId = null): void
    {
        $exists = ReadingPlan::query()
            ->where('user_id', $userId)
            ->where('book_id', $bookId)
            ->where('status', ReadingPlan::STATUS_IN_PROGRESS)
            ->when($ignorePlanId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'book_id' => 'この書籍には進行中の読書計画がすでに登録されています。',
            ]);
        }
    }
}
