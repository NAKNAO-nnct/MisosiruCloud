<?php

declare(strict_types=1);

namespace App\Providers;

use App\Data\User\AuthUserData;
use App\Lib\Dns\DnsProviderInterface;
use App\Lib\Dns\SakuraDnsProvider;
use App\Lib\Nomad\Client as NomadClient;
use App\Lib\Nomad\NomadApi;
use App\Lib\Proxmox\Client as ProxmoxClient;
use App\Lib\Proxmox\ProxmoxApi;
use App\Models\ProxmoxNode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DnsProviderInterface::class, fn (): DnsProviderInterface => new SakuraDnsProvider(
            baseUrl: (string) config('services.dns.sakura.base_url', ''),
            apiToken: (string) config('services.dns.sakura.api_token', ''),
            zone: (string) config('services.dns.sakura.zone', ''),
        ));

        $this->app->singleton(NomadApi::class, function (): ?NomadApi {
            $address = (string) config('services.nomad.address', '');
            $token = (string) config('services.nomad.token', '');

            if ($address === '' || $token === '') {
                return null;
            }

            $client = new NomadClient(
                address: $address,
                token: $token,
                verifyTls: (bool) config('services.nomad.verify_tls', false),
            );

            return new NomadApi($client);
        });

        $this->app->singleton(ProxmoxApi::class, function (): ?ProxmoxApi {
            $node = ProxmoxNode::where('is_active', true)->first();

            if (!$node) {
                return null;
            }

            $client = new ProxmoxClient(
                hostname: $node->hostname,
                tokenId: $node->api_token_id,
                tokenSecret: $node->api_token_secret_encrypted,
                verifyTls: !app()->isLocal(),
            );

            return new ProxmoxApi($client);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::anonymousComponentNamespace('components.layouts', 'layouts');
        View::composer('components.layouts.app', function ($view): void {
            $user = auth()->user();

            $view->with('authUser', $user ? AuthUserData::of($user) : null);
        });
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
