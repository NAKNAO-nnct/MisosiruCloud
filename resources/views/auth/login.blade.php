<x-layouts::guest :title="__('ログイン')">
    <div class="w-full max-w-md space-y-6 px-6">
        <div class="text-center">
            <flux:heading size="xl">{{ config('app.name') }}</flux:heading>
            <flux:subheading>メールアドレスとパスワードでログイン</flux:subheading>
        </div>

        @if (session('status'))
            <flux:callout variant="success" icon="check-circle">
                {{ session('status') }}
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <flux:field>
                <flux:label>メールアドレス</flux:label>
                <flux:input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" />
                <flux:error name="email" />
            </flux:field>

            <flux:field>
                <div class="flex items-center justify-between">
                    <flux:label>パスワード</flux:label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                            パスワードを忘れた方
                        </a>
                    @endif
                </div>
                <flux:input type="password" name="password" required autocomplete="current-password" />
                <flux:error name="password" />
            </flux:field>

            <div class="flex items-center gap-2">
                <flux:checkbox name="remember" id="remember" />
                <label for="remember" class="text-sm text-zinc-600 dark:text-zinc-400">ログイン状態を保持する</label>
            </div>

            <flux:button type="submit" variant="primary" class="w-full">
                ログイン
            </flux:button>
        </form>

        @if (Route::has('register'))
            <p class="text-center text-sm text-zinc-500">
                アカウントをお持ちでない方は
                <a href="{{ route('register') }}" class="text-zinc-700 underline hover:text-zinc-900 dark:text-zinc-300 dark:hover:text-zinc-100">
                    新規登録
                </a>
            </p>
        @endif
    </div>
</x-layouts::guest>
