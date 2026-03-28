<x-layouts::guest :title="__('パスワードを忘れた方')">
    <div class="w-full max-w-md space-y-6 px-6">
        <div class="text-center">
            <flux:heading size="xl">パスワードをリセット</flux:heading>
            <flux:subheading>
                登録済みのメールアドレスを入力してください。パスワードリセットのリンクをお送りします。
            </flux:subheading>
        </div>

        @if (session('status'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('status') }}
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf

            <flux:field>
                <flux:label>メールアドレス</flux:label>
                <flux:input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" />
                <flux:error name="email" />
            </flux:field>

            <flux:button type="submit" variant="primary" class="w-full">
                リセットリンクを送信
            </flux:button>
        </form>

        <p class="text-center text-sm text-zinc-500">
            <a href="{{ route('login') }}" class="text-zinc-700 underline hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100">
                ログインに戻る
            </a>
        </p>
    </div>
</x-layouts::guest>
