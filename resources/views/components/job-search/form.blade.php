<div class="bg-white rounded-lg shadow-lg p-8 mb-8">
    <form id="jobSearchForm" class="space-y-6">
        @csrf
        <div>
            <label for="keywords" class="block text-sm font-medium text-gray-700 mb-2">
                <i class="fas fa-key mr-2"></i>Search Keywords
            </label>
            <input 
                type="text" 
                id="keywords" 
                name="keywords" 
                placeholder="e.g., php, laravel, developer, remote, javascript, python, react"
                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
                autocomplete="off"
            >
            <div class="mt-2 space-y-1">
                <p class="text-sm text-gray-500">
                    <i class="fas fa-info-circle mr-1"></i>
                    Separate multiple keywords with commas
                </p>
                <x-job-search.popular-keywords />
                <div class="mt-2 text-xs text-gray-500">
                    <i class="fas fa-lightbulb mr-1"></i>
                    <strong>Tip:</strong> Use specific keywords like "php developer" or "laravel backend" for better results
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="city" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-map-marker-alt mr-2"></i>City <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <input
                    type="text"
                    id="city"
                    name="city"
                    maxlength="100"
                    placeholder="e.g., تهران, اصفهان, Tehran"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    autocomplete="off"
                >
                <p class="mt-1 text-xs text-gray-500">
                    Jobinja only lists locations in Persian, so use the Persian name to filter it.
                </p>
            </div>
            <div>
                <label for="sort" class="block text-sm font-medium text-gray-700 mb-2">
                    <i class="fas fa-sort-amount-down mr-2"></i>Sort by
                </label>
                <select
                    id="sort"
                    name="sort"
                    class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white"
                >
                    <option value="relevance">Relevance</option>
                    <option value="newest">Newest first</option>
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    Date sorting applies to Jobvision; Jobinja doesn't provide posting dates.
                </p>
            </div>
        </div>

        <x-ui.button
            type="submit" 
            id="searchBtn"
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-lg transition duration-300 flex items-center justify-center"
        >
            <i class="fas fa-search mr-2"></i>
            <span id="searchBtnText">Search Jobs</span>
            <div id="loadingSpinner" class="loading-spinner ml-2 hidden">
                <i class="fas fa-spinner"></i>
            </div>
        </x-ui.button>
    </form>
</div>
