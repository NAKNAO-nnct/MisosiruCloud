@props(['used', 'total', 'unit' => ''])

@php
    $percent = $total > 0 ? round(($used / $total) * 100) : 0;
    $color = match(true) {
        $percent >= 90 => 'bg-red-500',
        $percent >= 70 => 'bg-yellow-500',
        default        => 'bg-lime-500',
    };
@endphp

<div class="space-y-1">
    <div class="flex justify-between text-xs text-zinc-500">
        <span>{{ $used }}{{ $unit }} / {{ $total }}{{ $unit }}</span>
        <span>{{ $percent }}%</span>
    </div>
    <div class="h-2 w-full rounded-full bg-zinc-200 dark:bg-zinc-700">
        <div class="{{ $color }} h-2 rounded-full transition-all" style="width: {{ $percent }}%"></div>
    </div>
</div>
