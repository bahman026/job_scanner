@php
    $popularKeywords = ['php', 'developer', 'laravel', 'javascript', 'remote', 'python', 'react'];
@endphp

<div class="flex flex-wrap gap-2">
    <span class="text-xs text-gray-400">Popular searches:</span>
    @foreach($popularKeywords as $keyword)
        <button 
            type="button" 
            class="text-xs bg-gray-100 hover:bg-gray-200 px-2 py-1 rounded" 
            onclick="setKeyword('{{ $keyword }}')"
        >
            {{ $keyword }}
        </button>
    @endforeach
</div>

<script>
// Fallback function to ensure setKeyword is available immediately
if (typeof window.setKeyword === 'undefined') {
    window.setKeyword = function(keyword) {
        const keywordsInput = document.getElementById('keywords');
        if (!keywordsInput) {
            console.error('Keywords input not found');
            return;
        }
        
        const currentValue = keywordsInput.value.trim();
        
        if (currentValue === '') {
            keywordsInput.value = keyword;
        } else {
            // Add keyword if not already present
            const keywords = currentValue.split(',').map(k => k.trim());
            if (!keywords.includes(keyword)) {
                keywordsInput.value = currentValue + ', ' + keyword;
            }
        }
        
        keywordsInput.focus();
    };
}
</script>
