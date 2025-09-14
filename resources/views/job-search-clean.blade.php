<x-layout.app>
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <x-job-search.form />
            
            <x-job-results.section />
            
            <x-ui.error-message />
        </div>
    </div>

    @push('scripts')
        @vite(['resources/js/job-search.js'])
    @endpush
</x-layout.app>
