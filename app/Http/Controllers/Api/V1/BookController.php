<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreBookRequest;
use App\Http\Requests\Api\V1\UpdateBookRequest;
use App\Http\Resources\BookCollection;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * 検索条件に一致する書籍をレビュー評価と件数付きで取得し、ページネーションして返す。
     *
     * @param  IndexBookRequest  $request  検証済みの検索・絞り込み・並び替え・件数条件
     * @return BookCollection 書籍一覧のAPIリソースコレクション
     */
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

    /**
     * 認証ユーザーを所有者として書籍を登録し、ジャンルをトランザクション内で同期する。
     *
     * @param  StoreBookRequest  $request  検証済みの書籍情報とジャンルID
     * @return JsonResponse 作成した書籍を含むHTTP 201のJSONレスポンス
     *
     * @throws AuthorizationException 書籍の作成が許可されていない場合
     */
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

    /**
     * 所有者として更新を許可された書籍とジャンルをトランザクション内で更新する。
     *
     * @param  UpdateBookRequest  $request  検証済みの書籍情報とジャンルID
     * @param  Book  $book  更新対象の書籍
     * @return BookResource 更新後の書籍リソース
     *
     * @throws AuthorizationException 書籍の更新が許可されていない場合
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $this->authorize('update', $book);

        DB::transaction(function () use ($request, $book): void {
            $book->update($request->bookAttributes());
            $book->genres()->sync($request->genreIds());
        });

        return new BookResource($this->loadBookResponseData($book));
    }

    /**
     * 所有者として削除を許可された書籍をトランザクション内で削除する。
     *
     * @param  Book  $book  削除対象の書籍
     * @return JsonResponse 本文を含まないHTTP 204のJSONレスポンス
     *
     * @throws AuthorizationException 書籍の削除が許可されていない場合
     */
    public function destroy(Book $book): JsonResponse
    {
        $this->authorize('delete', $book);

        DB::transaction(fn (): bool => $book->delete());

        return response()->json(null, 204);
    }

    /**
     * APIレスポンスに必要なリレーションとレビュー集計値を書籍へ読み込む。
     *
     * 渡されたモデルへジャンル、必要に応じてレビューと投稿者を読み込み、
     * レビュー評価の平均値とレビュー件数を追加する。
     *
     * @param  Book  $book  レスポンスデータを読み込む書籍
     * @param  bool  $includeReviews  レビューと投稿者を追加で読み込むかどうか
     * @return Book レスポンス用データを読み込んだ書籍
     */
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
