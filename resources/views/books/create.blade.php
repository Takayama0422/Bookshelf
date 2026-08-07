<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('書籍の登録') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form id="isbn-search-form" class="mb-6">
                        <label for="isbn_search" class="block font-medium text-sm text-gray-700 mb-1">ISBNから書籍情報を検索</label>
                        <div class="flex gap-2">
                            <input type="text" name="isbn_search" id="isbn_search" value="{{ old('isbn', $bookData['isbn'] ?? '') }}"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full"
                                placeholder="ISBN-13（13桁）">
                            <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded whitespace-nowrap">
                                ISBNから検索
                            </button>
                        </div>
                        <p id="isbn-search-error" class="hidden text-sm text-red-600 mt-1"></p>
                    </form>

                    <form action="{{ route('books.store') }}" method="POST" novalidate>
                        @include('books._form', ['bookData' => $bookData])

                        <div class="flex items-center justify-end mt-6 pt-6 border-t border-gray-200">
                            <a href="{{ route('books.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
                                キャンセル
                            </a>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
                                登録
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
    <script>
        document.getElementById('isbn-search-form').addEventListener('submit', async (event) => {
            event.preventDefault();

            const input = document.getElementById('isbn_search');
            const error = document.getElementById('isbn-search-error');
            const isbn = input.value.replace(/[\s-]/g, '');
            error.classList.add('hidden');

            if (!/^\d{13}$/.test(isbn)) {
                error.textContent = 'ISBNは13桁で入力してください。';
                error.classList.remove('hidden');
                return;
            }

            try {
                const response = await fetch('/books/isbn/' + encodeURIComponent(isbn), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.error ?? 'API通信エラーが発生しました。');
                }

                for (const [field, value] of Object.entries(data)) {
                    const element = document.getElementById(field);
                    if (element && value !== null) {
                        element.value = value;
                    }
                }
            } catch (exception) {
                error.textContent = exception.message;
                error.classList.remove('hidden');
            }
        });
    </script>
@endpush
