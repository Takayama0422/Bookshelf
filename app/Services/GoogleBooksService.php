<?php

namespace App\Services;

use App\Exceptions\IsbnApiResponseException;
use App\Exceptions\IsbnApiUnavailableException;
use App\Exceptions\IsbnBookNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleBooksService
{
    private const ENDPOINT = 'https://www.googleapis.com/books/v1/volumes';

    /** @return array{title: string, author: string, published_date: ?string, description: string, image_url: string, isbn: string} */
    public function search(string $isbn): array
    {
        $normalizedIsbn = IsbnNormalizer::normalize($isbn);

        try {
            $response = Http::timeout(5)->get(self::ENDPOINT, [
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
