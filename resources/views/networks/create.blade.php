<x-layouts::app :title="__('ネットワーク作成')">
    <div class="flex flex-col gap-6">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('networks.index') }}" variant="ghost" size="sm" icon="arrow-left">一覧へ戻る</flux:button>
            <flux:heading size="xl">ネットワーク作成</flux:heading>
        </div>

        <flux:card>
            <form method="POST" action="{{ route('networks.store') }}" class="grid grid-cols-1 gap-4 md:grid-cols-2">
                @csrf
                <flux:select name="tenant_id" label="テナント" required>
                    <option value="">選択してください</option>
                    @foreach ($tenants as $tenant)
                        <option value="{{ $tenant->getId() }}" @selected((string) old('tenant_id') === (string) $tenant->getId())>
                            {{ $tenant->getName() }} ({{ $tenant->getSlug() }})
                        </option>
                    @endforeach
                </flux:select>

                <flux:input
                    name="network_cidr"
                    label="CIDR"
                    value="{{ old('network_cidr') }}"
                    placeholder="10.50.0.0/24"
                    required
                />

                <div class="md:col-span-2">
                    <flux:button type="submit" variant="primary">作成</flux:button>
                </div>
            </form>
        </flux:card>
    </div>
</x-layouts::app>
