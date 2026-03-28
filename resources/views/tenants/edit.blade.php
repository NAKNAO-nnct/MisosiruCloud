<x-layouts::app :title="'テナント編集: ' . $tenant->getName()">
    <div class="flex flex-col gap-6 max-w-2xl">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('tenants.show', $tenant->getId()) }}" variant="ghost" size="sm" icon="arrow-left">
                詳細へ戻る
            </flux:button>
            <flux:heading size="xl">テナント編集</flux:heading>
        </div>

        <form method="POST" action="{{ route('tenants.update', $tenant->getId()) }}" class="flex flex-col gap-4">
            @csrf
            @method('PUT')

            <flux:field>
                <flux:label>名前 <span class="text-red-500">*</span></flux:label>
                <flux:input name="name" value="{{ old('name', $tenant->getName()) }}" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>スラッグ <span class="text-red-500">*</span></flux:label>
                <flux:input name="slug" value="{{ old('slug', $tenant->getSlug()) }}" required />
                <flux:description>小文字英数字とハイフンのみ使用できます。</flux:description>
                <flux:error name="slug" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">更新</flux:button>
                <flux:button href="{{ route('tenants.show', $tenant->getId()) }}" variant="ghost">キャンセル</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
