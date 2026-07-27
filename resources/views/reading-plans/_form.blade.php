@csrf

<div class="space-y-6">
    <div>
        <label for="book_id" class="block font-medium text-sm text-gray-700 mb-1">
            書籍 <span class="text-red-500">*</span>
        </label>
        <select name="book_id" id="book_id" class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full">
            <option value="">選択してください</option>
            @foreach($books as $book)
                <option value="{{ $book->id }}" @selected((string) old('book_id', $readingPlan->book_id ?? '') === (string) $book->id)>
                    {{ $book->title }}
                </option>
            @endforeach
        </select>
        @error('book_id')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="target_date" class="block font-medium text-sm text-gray-700 mb-1">
            目標読了日 <span class="text-red-500">*</span>
        </label>
        <input type="date" name="target_date" id="target_date" value="{{ old('target_date', isset($readingPlan) ? $readingPlan->target_date->toDateString() : '') }}"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full">
        @error('target_date')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>
