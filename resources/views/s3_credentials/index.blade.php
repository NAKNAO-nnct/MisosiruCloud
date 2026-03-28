<x-layouts::app :title="'S3認証情報 - ' . $tenant->getName()">
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
                <flux:button href="{{ route('tenants.show', $tenant->getId()) }}" variant="ghost" size="sm" icon="arrow-left">
                    テナント詳細へ戻る
                </flux:button>
                <flux:heading size="xl">S3認証情報 — {{ $tenant->getName() }}</flux:heading>
            </div>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('success') }}
            </flux:callout>
        @endif

        <flux:card>
            <flux:heading size="lg">新しい認証情報を追加</flux:heading>
            <form method="POST" action="{{ route('tenants.s3-credentials.store', $tenant->getId()) }}"
                class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">
                @csrf
                <flux:field>
                    <flux:label>バケット <span class="text-red-500">*</span></flux:label>
                    <flux:input name="allowed_bucket" value="{{ old('allowed_bucket') }}" required />
                    <flux:error name="allowed_bucket" />
                </flux:field>
                <flux:field>
                    <flux:label>プレフィックス <span class="text-red-500">*</span></flux:label>
                    <flux:input name="allowed_prefix" value="{{ old('allowed_prefix', $tenant->getSlug() . '/') }}" required />
                    <flux:error name="allowed_prefix" />
                </flux:field>
                <flux:field>
                    <flux:label>説明</flux:label>
                    <flux:input name="description" value="{{ old('description') }}" />
                </flux:field>
                <div class="md:col-span-3">
                    <flux:button type="submit" variant="primary">追加</flux:button>
                </div>
            </form>
        </flux:card>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>アクセスキー</flux:table.column>
                <flux:table.column>バケット/プレフィックス</flux:table.column>
                <flux:table.column>説明</flux:table.column>
                <flux:table.column>ステータス</flux:table.column>
                <flux:table.column>操作</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($credentials as $credential)
                    <flux:table.row>
                        <flux:table.cell class="font-mono text-sm">{{ $credential->getAccessKey() }}</flux:table.cell>
                        <flux:table.cell class="text-sm">{{ $credential->getAllowedBucket() }}/{{ $credential->getAllowedPrefix() }}</flux:table.cell>
                        <flux:table.cell>{{ $credential->getDescription() ?? '-' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge variant="{{ $credential->isActive() ? 'lime' : 'zinc' }}">
                                {{ $credential->isActive() ? '有効' : '無効' }}
                            </flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            <div class="flex gap-2">
                                <flux:button href="{{ route('tenants.s3-credentials.show', [$tenant->getId(), $credential->getId()]) }}" size="sm" variant="ghost">
                                    詳細
                                </flux:button>
                                @if ($credential->isActive())
                                    <form method="POST" action="{{ route('tenants.s3-credentials.destroy', [$tenant->getId(), $credential->getId()]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" size="sm" variant="danger">無効化</flux:button>
                                    </form>
                                @endif
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="text-center text-zinc-500">
                            認証情報がありません。
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div>{{ $credentials->links() }}</div>
    </div>
</x-layouts::app>
