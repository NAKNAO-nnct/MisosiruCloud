@props(['status'])

@php
    $variant = match($status?->value) {
        'ready'       => 'lime',
        'pending'     => 'yellow',
        'cloning'     => 'blue',
        'configuring' => 'blue',
        'starting'    => 'sky',
        'error'       => 'red',
        default       => 'zinc',
    };
    $label = match($status?->value) {
        'ready'       => '稼働中',
        'pending'     => '待機中',
        'cloning'     => 'クローン中',
        'configuring' => '設定中',
        'starting'    => '起動中',
        'error'       => 'エラー',
        default       => $status?->value ?? '不明',
    };
@endphp

<flux:badge variant="{{ $variant }}">{{ $label }}</flux:badge>
