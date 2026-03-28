<x-layouts::app :title="__('コンテナ詳細')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('containers.index') }}" variant="ghost" size="sm" icon="arrow-left">一覧へ戻る</flux:button>
                <flux:heading size="xl">{{ $job->getName() }} (#{{ $job->getId() }})</flux:heading>
                <flux:badge variant="{{ $status === 'running' ? 'lime' : ($status === 'pending' ? 'amber' : 'zinc') }}">{{ $status }}</flux:badge>
            </div>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <flux:card>
                <flux:heading size="lg">基本情報</flux:heading>
                <dl class="mt-2 flex flex-col gap-2 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-500">テナント</dt><dd>{{ $tenantName }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Nomad Job ID</dt><dd class="font-mono">{{ $job->getNomadJobId() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">イメージ</dt><dd>{{ $job->getImage() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">ドメイン</dt><dd>{{ $job->getDomain() ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">レプリカ</dt><dd>{{ $job->getReplicas() }}</dd></div>
                </dl>
            </flux:card>

            <flux:card>
                <flux:heading size="lg">リソース</flux:heading>
                <dl class="mt-2 flex flex-col gap-2 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-500">CPU (MHz)</dt><dd>{{ $job->getCpuMhz() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">メモリ (MB)</dt><dd>{{ $job->getMemoryMb() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">アロケーション数</dt><dd>{{ count($allocations) }}</dd></div>
                </dl>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg" class="mb-4">操作</flux:heading>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('containers.restart', $job->getId()) }}">
                    @csrf
                    <flux:button type="submit">再起動</flux:button>
                </form>

                <form method="POST" action="{{ route('containers.scale', $job->getId()) }}" class="flex gap-2">
                    @csrf
                    <flux:input type="number" name="replicas" min="1" value="{{ $job->getReplicas() }}" class="max-w-24" />
                    <flux:button type="submit">スケール</flux:button>
                </form>

                <form method="POST" action="{{ route('containers.destroy', $job->getId()) }}" onsubmit="return confirm('このコンテナを削除しますか？')">
                    @csrf
                    @method('DELETE')
                    <flux:button type="submit" variant="danger">削除</flux:button>
                </form>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">ログ</flux:heading>
            <form method="GET" action="{{ route('containers.show', $job->getId()) }}" class="mt-3 flex gap-2">
                <flux:input name="task_name" value="{{ $taskName }}" placeholder="task name" class="max-w-48" />
                <flux:button type="submit" variant="ghost">表示</flux:button>
                <flux:button href="{{ route('containers.logs', ['container' => $job->getId(), 'task_name' => $taskName]) }}" variant="ghost">Raw</flux:button>
            </form>
            <pre class="mt-3 max-h-80 overflow-auto rounded bg-zinc-900 p-3 text-xs text-zinc-100">{{ $logs !== '' ? $logs : 'ログはありません。' }}</pre>
        </flux:card>
    </div>
</x-layouts::app>
