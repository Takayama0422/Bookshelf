<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\IndexReadingPlanRequest;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
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
