<?php

namespace App\Http\Controllers;

use App\Exceptions\IsbnApiResponseException;
use App\Exceptions\IsbnApiUnavailableException;
use App\Exceptions\IsbnBookNotFoundException;
use App\Exceptions\IsbnQuotaExceededException;
use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Services\GoogleBooksService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

class BookController extends Controller
{
    /**
     * 検索条件に一致する書籍をレビュー評価と件数付きで取得し、指定順で一覧表示する。
     *
     * @param  IndexBookRequest  $request  検証済みの検索・絞り込み・並び替え条件
     * @return View 書籍一覧画面
     */
    public function index(IndexBookRequest $request): View
    {
        $books = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($request->keyword(), function ($query, string $keyword): void {
                $query->where(function ($query) use ($keyword): void {
                    $query->where('title', 'like', "%{$keyword}%")
                        ->orWhere('author', 'like', "%{$keyword}%");
                });
            })
            ->when($request->genreId(), function ($query, int $genreId): void {
                $query->whereHas('genres', fn ($query) => $query->whereKey($genreId));
            });

        match ($request->sort()) {
            'oldest' => $books->oldest()->orderBy('id'),
            'title' => $books->orderBy('title')->orderBy('id'),
            'rating' => $books
                ->orderByRaw('reviews_avg_rating IS NULL')
                ->orderByDesc('reviews_avg_rating')
                ->orderByDesc('reviews_count')
                ->orderBy('id'),
            default => $books->latest()->orderBy('id'),
        };

        $books = $books->paginate(10)->withQueryString();
        $genres = Genre::query()->orderBy('id')->get();
        $filters = $request->filters();

        return view('books.index', compact('books', 'genres', 'filters'));
    }

    public function show(Book $book): View
    {
        $book->load([
            'genres',
            'reviews.user',
            'reviews.likedByUsers',
        ]);

        return view('books.show', compact('book'));
    }

    public function create(): View
    {
        $this->authorize('create', Book::class);

        $genres = Genre::query()->orderBy('id')->get();

        return view('books.create', ['genres' => $genres, 'bookData' => []]);
    }

    /**
     * ISBNを使用してGoogle Books APIから書籍情報を検索し、書籍登録画面へ反映する。
     *
     * API上で書籍が見つからない場合や通信・レスポンス異常の場合は、
     * 例外を利用者向けメッセージへ変換したJSONレスポンスを返す。
     *
     * @param  string  $isbn  検索対象のISBN-13
     * @param  GoogleBooksService  $googleBooks  Google Books API検索サービス
     * @return JsonResponse 検索結果または日本語エラーを含むJSONレスポンス
     */
    public function searchByIsbn(string $isbn, GoogleBooksService $googleBooks): JsonResponse
    {
        if (preg_match('/^\d{13}$/', $isbn) !== 1) {
            return response()->json(['error' => 'ISBNは13桁で入力してください。'], 400);
        }

        try {
            $bookData = $googleBooks->search($isbn);
        } catch (InvalidArgumentException) {
            return response()->json(['error' => 'ISBNは13桁で入力してください。'], 400);
        } catch (IsbnBookNotFoundException) {
            return response()->json(['error' => '書籍が見つかりませんでした。'], 404);
        } catch (IsbnQuotaExceededException) {
            return response()->json([
                'error' => 'Google Books API のクォータを超過しました。.env に GOOGLE_BOOKS_API_KEY を設定してください。',
            ], 429);
        } catch (IsbnApiUnavailableException) {
            return response()->json(['error' => 'API通信エラーが発生しました。'], 500);
        } catch (IsbnApiResponseException) {
            return response()->json(['error' => 'API通信エラーが発生しました。'], 500);
        }

        return response()->json($bookData);
    }

    public function store(StoreBookRequest $request): RedirectResponse
    {
        $this->authorize('create', Book::class);

        $book = DB::transaction(function () use ($request): Book {
            $book = $request->user()->books()->create($request->bookAttributes());
            $book->genres()->sync($request->genreIds());

            return $book;
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $book->load('genres');
        $genres = Genre::query()->orderBy('id')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        DB::transaction(function () use ($request, $book): void {
            $book->update($request->bookAttributes());
            $book->genres()->sync($request->genreIds());
        });

        return redirect()
            ->route('books.show', $book)
            ->with('success', '書籍を更新しました。');
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        DB::transaction(fn (): bool => $book->delete());

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
