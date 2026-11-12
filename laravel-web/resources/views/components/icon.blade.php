@props(['name'])

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" {{ $attributes->merge(['class' => 'w-4 h-4']) }}>
    {!! \App\Support\Icons::inner($name) !!}
</svg>
