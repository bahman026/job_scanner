<button 
    {{ $attributes->merge(['type' => 'button']) }}
    class="{{ $attributes->get('class') }}"
>
    {{ $slot }}
</button>
