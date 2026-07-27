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
                    @if(isset($error))
                        <p class="mb-6 text-sm text-red-600">{{ $error }}</p>
                    @endif

                    <form action="{{ route('books.isbn-search') }}" method="GET" class="mb-6">
                        <label for="isbn_search" class="block font-medium text-sm text-gray-700 mb-1">ISBNから書籍情報を検索</label>
                        <div class="flex gap-2">
                            <input type="text" name="isbn" id="isbn_search" value="{{ old('isbn', $bookData['isbn'] ?? request('isbn', '')) }}"
                                class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full"
                                placeholder="ISBN-10またはISBN-13">
                            <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded whitespace-nowrap">
                                ISBNから検索
                            </button>
                        </div>
                        @error('isbn')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror
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
