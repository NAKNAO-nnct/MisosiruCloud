<x-layouts::app :title="__('テナント作成')">
    <div class="flex flex-col gap-6 max-w-2xl">
        <div class="flex items-center gap-2">
            <flux:button href="{{ route('tenants.index') }}" variant="ghost" size="sm" icon="arrow-left">
                一覧へ戻る
            </flux:button>
            <flux:heading size="xl">テナント作成</flux:heading>
        </div>

        @if ($errors->has('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">
                {{ $errors->first('error') }}
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('tenants.store') }}" class="flex flex-col gap-4">
            @csrf

            <flux:field>
                <flux:label>名前 <flux:required /></flux:label>
                <flux:input name="name" value="{{ old('name') }}" required />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>スラッグ <flux:required /></flux:label>
                <flux:input name="slug" value="{{ old('slug') }}" placeholder="my-tenant" required />
                <flux:description>小文字英数字とハイフンのみ使用できます。</flux:description>
                <flux:error name="slug" />
            </flux:field>

            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">作成</flux:button>
                <flux:button href="{{ route('tenants.index') }}" variant="ghost">キャンセル</flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
