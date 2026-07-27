<?php

namespace App\Http\Controllers;

use App\Exceptions\IsbnApiResponseException;
use App\Exceptions\IsbnApiUnavailableException;
use App\Exceptions\IsbnBookNotFoundException;
use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\IsbnSearchRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Services\GoogleBooksService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookController extends Controller
{
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

    public function isbnSearch(IsbnSearchRequest $request, GoogleBooksService $googleBooks): View
    {
        $genres = Genre::query()->orderBy('id')->get();

        try {
            $bookData = $googleBooks->search($request->validated('isbn'));
        } catch (IsbnBookNotFoundException) {
            return view('books.create', ['genres' => $genres, 'bookData' => []])
                ->with('error', 'ISBNに該当する書籍情報が見つかりません。');
        } catch (IsbnApiUnavailableException) {
            return view('books.create', ['genres' => $genres, 'bookData' => []])
                ->with('error', '書籍情報サービスに接続できませんでした。時間をおいて再度お試しください。');
        } catch (IsbnApiResponseException) {
            return view('books.create', ['genres' => $genres, 'bookData' => []])
                ->with('error', '書籍情報を取得できませんでした。時間をおいて再度お試しください。');
        }

        return view('books.create', compact('genres', 'bookData'));
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

        $book->delete();

        return redirect()
            ->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
