<x-layouts::guest :title="__('パスワードリセット')">
    <div class="w-full max-w-md space-y-6 px-6">
        <div class="text-center">
            <flux:heading size="xl">新しいパスワードを設定</flux:heading>
            <flux:subheading>新しいパスワードを入力してください。</flux:subheading>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <flux:field>
                <flux:label>メールアドレス</flux:label>
                <flux:input type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="email" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <flux:label>新しいパスワード</flux:label>
                <flux:input type="password" name="password" required autocomplete="new-password" />
                <flux:error name="password" />
            </flux:field>

            <flux:field>
                <flux:label>新しいパスワード（確認）</flux:label>
                <flux:input type="password" name="password_confirmation" required autocomplete="new-password" />
                <flux:error name="password_confirmation" />
            </flux:field>

            <flux:button type="submit" variant="primary" class="w-full">
                パスワードをリセット
            </flux:button>
        </form>
    </div>
</x-layouts::guest>
