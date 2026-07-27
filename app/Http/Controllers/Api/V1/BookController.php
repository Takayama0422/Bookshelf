<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\BookCollection;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    public function index(IndexBookRequest $request): BookCollection
    {
        $books = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($request->keyword(), function ($query, string $keyword): void {
                $query->where(function ($query) use ($keyword): void {
                    $query->where('title', 'like', "%{$keyword}%")
                        ->orWhere('author', 'like', "%{$keyword}%")
                        ->orWhere('isbn', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->when($request->genreId(), function ($query, int $genreId): void {
                $query->whereHas('genres', fn ($query) => $query->whereKey($genreId));
            });

        match ($request->sort()) {
            'oldest' => $books->oldest(),
            'title' => $books->orderBy('title')->orderBy('id'),
            'rating' => $books
                ->orderByRaw('reviews_avg_rating IS NULL')
                ->orderByDesc('reviews_avg_rating')
                ->orderByDesc('reviews_count')
                ->orderBy('id'),
            default => $books->latest(),
        };

        return new BookCollection($books->paginate($request->perPage()));
    }

    public function store(StoreBookRequest $request): JsonResponse
    {
        $this->authorize('create', Book::class);

        $book = DB::transaction(function () use ($request): Book {
            $book = $request->user()->books()->create($request->bookAttributes());
            $book->genres()->sync($request->genreIds());

            return $book;
        });

        return (new BookResource($this->loadBookResponseData($book)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Book $book): BookResource
    {
        return new BookResource($this->loadBookResponseData($book, includeReviews: true));
    }

    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $this->authorize('update', $book);

        DB::transaction(function () use ($request, $book): void {
            $book->update($request->bookAttributes());
            $book->genres()->sync($request->genreIds());
        });

        return new BookResource($this->loadBookResponseData($book));
    }

    public function destroy(Book $book): JsonResponse
    {
        $this->authorize('delete', $book);

        DB::transaction(fn (): bool => $book->delete());

        return response()->json(null, 204);
    }

    private function loadBookResponseData(Book $book, bool $includeReviews = false): Book
    {
        $relations = ['genres'];

        if ($includeReviews) {
            $relations[] = 'reviews.user';
        }

        return $book->load($relations)
            ->loadAvg('reviews', 'rating')
            ->loadCount('reviews');
    }
}
