<x-layouts::guest :title="__('二段階認証')">
    <div class="w-full max-w-md space-y-6 px-6">
        <div class="text-center">
            <flux:heading size="xl">二段階認証</flux:heading>
            <flux:subheading>
                認証アプリのコードを入力するか、残りのリカバリコードのいずれかを入力してください。
            </flux:subheading>
        </div>

        <div x-data="{ recovery: false }">
            <form method="POST" action="{{ route('two-factor.login.store') }}" class="space-y-4">
                @csrf

                <div x-show="!recovery">
                    <flux:field>
                        <flux:label>認証コード</flux:label>
                        <flux:input
                            type="text"
                            name="code"
                            inputmode="numeric"
                            autofocus
                            autocomplete="one-time-code"
                        />
                        <flux:error name="code" />
                    </flux:field>
                </div>

                <div x-show="recovery">
                    <flux:field>
                        <flux:label>リカバリコード</flux:label>
                        <flux:input
                            type="text"
                            name="recovery_code"
                            autocomplete="one-time-code"
                        />
                        <flux:error name="recovery_code" />
                    </flux:field>
                </div>

                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        class="text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300"
                        x-on:click="recovery = !recovery"
                    >
                        <span x-show="!recovery">リカバリコードを使用する</span>
                        <span x-show="recovery">認証コードを使用する</span>
                    </button>

                    <flux:button type="submit" variant="primary">
                        確認
                    </flux:button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::guest>
