<?php

namespace App\Services;

use App\Exceptions\IsbnApiResponseException;
use App\Exceptions\IsbnApiUnavailableException;
use App\Exceptions\IsbnBookNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Throwable;

class GoogleBooksService
{
    /**
     * ISBNを正規化し、設定済みの接続先とタイムアウトでGoogle Books APIを検索する。
     *
     * @param  string  $isbn  検索するISBN
     * @return array{title: string, author: string, published_date: ?string, description: string, image_url: string, isbn: string} 登録画面で使用する書籍情報
     *
     * @throws InvalidArgumentException ISBNが不正な場合
     * @throws IsbnApiUnavailableException APIへ接続できない場合
     * @throws IsbnApiResponseException API応答または書籍情報の形式が不正な場合
     * @throws IsbnBookNotFoundException ISBNに一致する書籍がない場合
     */
    public function search(string $isbn): array
    {
        $normalizedIsbn = IsbnNormalizer::normalize($isbn);

        try {
            $response = Http::timeout((int) config('services.google_books.timeout'))->get(config('services.google_books.endpoint'), [
                'q' => 'isbn:'.$normalizedIsbn,
            ]);
        } catch (ConnectionException $exception) {
            throw new IsbnApiUnavailableException(previous: $exception);
        } catch (Throwable $exception) {
            throw new IsbnApiUnavailableException(previous: $exception);
        }

        if ($response->serverError() || $response->clientError() || ! $response->successful()) {
            throw new IsbnApiResponseException;
        }

        $payload = $response->json();
        if (! is_array($payload) || ! array_key_exists('items', $payload) || ! is_array($payload['items'])) {
            throw new IsbnApiResponseException;
        }

        if ($payload['items'] === []) {
            throw new IsbnBookNotFoundException;
        }

        $firstItem = $payload['items'][0] ?? null;
        $volumeInfo = is_array($firstItem) ? ($firstItem['volumeInfo'] ?? null) : null;
        if (! is_array($volumeInfo)) {
            throw new IsbnApiResponseException;
        }

        $imageLinks = is_array($volumeInfo['imageLinks'] ?? null) ? $volumeInfo['imageLinks'] : [];

        return [
            'title' => is_string($volumeInfo['title'] ?? null) ? $volumeInfo['title'] : '',
            'author' => is_array($volumeInfo['authors'] ?? null) ? implode(', ', array_filter($volumeInfo['authors'], 'is_string')) : '',
            'published_date' => $this->publishedDate($volumeInfo['publishedDate'] ?? null),
            'description' => is_string($volumeInfo['description'] ?? null) ? $volumeInfo['description'] : '',
            'image_url' => is_string($imageLinks['thumbnail'] ?? null) ? $imageLinks['thumbnail'] : '',
            'isbn' => $normalizedIsbn,
        ];
    }

    /**
     * APIの出版日を日付形式へ変換し、年月または年だけの場合は月初または年初で補完する。
     *
     * @param  mixed  $publishedDate  APIから取得した出版日
     * @return string|null 正規化した日付。不正または空の場合はnull
     */
    private function publishedDate(mixed $publishedDate): ?string
    {
        if (! is_string($publishedDate) || $publishedDate === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $publishedDate) === 1) {
            return $publishedDate;
        }
        if (preg_match('/^\d{4}-\d{2}$/', $publishedDate) === 1) {
            return $publishedDate.'-01';
        }
        if (preg_match('/^\d{4}$/', $publishedDate) === 1) {
            return $publishedDate.'-01-01';
        }

        return null;
    }
}
