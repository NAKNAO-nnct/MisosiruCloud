@props([
    'href' => null,
    'method' => 'GET',
    'variant' => 'primary',
    'confirm' => null,
])

@if (strtoupper($method) === 'GET')
    <flux:button href="{{ $href }}" :variant="$variant" {{ $attributes }}>
        {{ $slot }}
    </flux:button>
@else
    <form method="POST" action="{{ $href }}" class="inline" @if($confirm) onsubmit="return confirm('{{ $confirm }}')" @endif>
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif
        <flux:button type="submit" :variant="$variant" {{ $attributes->except(['href']) }}>
            {{ $slot }}
        </flux:button>
    </form>
@endif
