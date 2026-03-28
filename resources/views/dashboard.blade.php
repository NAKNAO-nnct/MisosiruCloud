<x-layouts::app :title="__('ダッシュボード')">
    <div class="flex flex-col gap-4">
        <flux:heading size="xl">ダッシュボード</flux:heading>
        <flux:text>{{ config('app.name') }} へようこそ。</flux:text>
    </div>
</x-layouts::app>
