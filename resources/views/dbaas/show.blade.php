<x-layouts::app :title="__('DBaaS 詳細')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('dbaas.index') }}" variant="ghost" size="sm" icon="arrow-left">一覧へ戻る</flux:button>
                <flux:heading size="xl">DB #{{ $database->getId() }} ({{ $database->getDbType()->value }})</flux:heading>
                <flux:badge variant="{{ $database->getStatus() === 'running' ? 'lime' : ($database->getStatus() === 'stopped' ? 'zinc' : 'amber') }}">{{ $database->getStatus() }}</flux:badge>
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
                    <div class="flex justify-between"><dt class="text-zinc-500">DB種別</dt><dd>{{ $database->getDbType()->value }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">バージョン</dt><dd>{{ $database->getDbVersion() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">ポート</dt><dd>{{ $database->getPort() }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">VM Meta ID</dt><dd>{{ $database->getVmMetaId() }}</dd></div>
                </dl>
            </flux:card>

            <flux:card>
                <flux:heading size="lg">接続情報</flux:heading>
                <dl class="mt-2 flex flex-col gap-2 text-sm">
                    <div class="flex justify-between"><dt class="text-zinc-500">Host</dt><dd>{{ $connection['host'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Port</dt><dd>{{ $connection['port'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Admin User</dt><dd>{{ $connection['admin_user'] }}</dd></div>
                    <div class="flex justify-between"><dt class="text-zinc-500">Admin Password</dt><dd class="font-mono">{{ $connection['admin_password'] }}</dd></div>
                </dl>
            </flux:card>
        </div>

        <flux:card>
            <flux:heading size="lg" class="mb-4">操作</flux:heading>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('dbaas.start', $database->getId()) }}">@csrf <flux:button type="submit" variant="primary">起動</flux:button></form>
                <form method="POST" action="{{ route('dbaas.stop', $database->getId()) }}">@csrf <flux:button type="submit" variant="warning">停止</flux:button></form>
                <form method="POST" action="{{ route('dbaas.backup', $database->getId()) }}">@csrf <flux:button type="submit">バックアップ実行</flux:button></form>
                <form method="POST" action="{{ route('dbaas.upgrade', $database->getId()) }}" class="flex gap-2">@csrf <flux:input name="db_version" placeholder="8.4" class="max-w-32" /><flux:button type="submit">アップグレード</flux:button></form>
                <form method="POST" action="{{ route('dbaas.destroy', $database->getId()) }}" onsubmit="return confirm('このDBインスタンスを削除しますか？')">@csrf @method('DELETE') <flux:button type="submit" variant="danger">削除</flux:button></form>
            </div>
        </flux:card>

        <flux:card>
            <flux:heading size="lg">バックアップ</flux:heading>
            <p class="mt-2 text-sm text-zinc-500">{{ count($backups) }} 件</p>
            @if ($backupSchedule)
                <p class="mt-1 text-sm text-zinc-500">最終状態: {{ $backupSchedule->getLastBackupStatus() ?? '-' }}</p>
            @endif
            <div class="mt-4 flex flex-col gap-2">
                @forelse ($backups as $backup)
                    <div class="flex items-center justify-between rounded border border-zinc-200 p-2 text-sm">
                        <span class="font-mono">{{ $backup['key'] ?? '-' }}</span>
                        <form method="POST" action="{{ route('dbaas.restore', $database->getId()) }}" class="flex gap-2">
                            @csrf
                            <input type="hidden" name="s3_key" value="{{ $backup['key'] ?? '' }}" />
                            <flux:button type="submit" size="sm" variant="ghost">リストア</flux:button>
                        </form>
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">バックアップはまだありません。</p>
                @endforelse
            </div>
        </flux:card>
    </div>
</x-layouts::app>
