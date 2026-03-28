<x-layouts::app :title="'S3認証情報詳細'">
    <div class="flex flex-col gap-6 max-w-2xl">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('tenants.s3-credentials.index', $tenant->getId()) }}" variant="ghost" size="sm" icon="arrow-left">
                一覧へ戻る
            </flux:button>
            <flux:heading size="xl">S3認証情報詳細</flux:heading>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        <flux:card>
            <dl class="flex flex-col gap-4 text-sm">
                <div>
                    <dt class="text-zinc-500 mb-1">アクセスキー</dt>
                    <dd class="font-mono">{{ $s3Credential->getAccessKey() }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 mb-1">シークレットキー</dt>
                    <dd x-data="{ show: false }">
                        <div class="flex items-center gap-2">
                            <span class="font-mono" x-text="show ? '{{ $s3Credential->getSecretKey() }}' : '••••••••••••••••••••'"></span>
                            <flux:button size="sm" variant="ghost" x-on:click="show = !show" x-text="show ? '隠す' : '表示'">
                                表示
                            </flux:button>
                        </div>
                    </dd>
                </div>
                <div>
                    <dt class="text-zinc-500 mb-1">バケット</dt>
                    <dd>{{ $s3Credential->getAllowedBucket() }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 mb-1">プレフィックス</dt>
                    <dd>{{ $s3Credential->getAllowedPrefix() }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 mb-1">説明</dt>
                    <dd>{{ $s3Credential->getDescription() ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-zinc-500 mb-1">ステータス</dt>
                    <dd>
                        <flux:badge variant="{{ $s3Credential->isActive() ? 'lime' : 'zinc' }}">
                            {{ $s3Credential->isActive() ? '有効' : '無効' }}
                        </flux:badge>
                    </dd>
                </div>
            </dl>

            <div class="mt-6 flex gap-2">
                @if ($s3Credential->isActive())
                    <form method="POST" action="{{ route('tenants.s3-credentials.rotate', [$tenant->getId(), $s3Credential->getId()]) }}">
                        @csrf
                        @method('PUT')
                        <flux:button type="submit" variant="primary">シークレットキーをローテーション</flux:button>
                    </form>
                    <form method="POST" action="{{ route('tenants.s3-credentials.destroy', [$tenant->getId(), $s3Credential->getId()]) }}"
                        onsubmit="return confirm('この認証情報を無効化しますか？')">
                        @csrf
                        @method('DELETE')
                        <flux:button type="submit" variant="danger">無効化</flux:button>
                    </form>
                @endif
            </div>
        </flux:card>
    </div>
</x-layouts::app>
