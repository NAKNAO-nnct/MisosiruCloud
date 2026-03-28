<x-layouts::guest :title="__('パスワードの確認')">
    <div class="w-full max-w-md space-y-6 px-6">
        <div class="text-center">
            <flux:heading size="xl">パスワードの確認</flux:heading>
            <flux:subheading>
                続行するにはパスワードを確認してください。
            </flux:subheading>
        </div>

        <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
            @csrf

            <flux:field>
                <flux:label>パスワード</flux:label>
                <flux:input type="password" name="password" required autocomplete="current-password" autofocus />
                <flux:error name="password" />
            </flux:field>

            <flux:button type="submit" variant="primary" class="w-full">
                確認
            </flux:button>
        </form>
    </div>
</x-layouts::guest>
