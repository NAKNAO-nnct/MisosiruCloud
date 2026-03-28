<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CreateTenantRequest;
use App\Lib\Proxmox\ProxmoxApi;
use App\Models\Tenant;
use App\Services\S3CredentialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StoreController extends Controller
{
    public function __construct(
        private readonly S3CredentialService $s3Service,
        private readonly ?ProxmoxApi $proxmoxApi,
    ) {
    }

    public function __invoke(CreateTenantRequest $request): RedirectResponse
    {
        try {
            $tenant = DB::transaction(function () use ($request): Tenant {
                $tenant = Tenant::create([
                    'uuid' => Str::uuid()->toString(),
                    'name' => $request->name,
                    'slug' => $request->slug,
                ]);

                $vni = 10000 + $tenant->id;
                $vnetName = "tenant-{$tenant->id}";
                $networkCidr = "10.{$tenant->id}.0.0/24";

                $tenant->update([
                    'vni' => $vni,
                    'vnet_name' => $vnetName,
                    'network_cidr' => $networkCidr,
                    'nomad_namespace' => $tenant->slug,
                ]);

                if ($this->proxmoxApi) {
                    $this->proxmoxApi->cluster()->createVnet([
                        'vnet' => $vnetName,
                        'zone' => 'localzone',
                        'tag' => $vni,
                    ]);

                    $this->proxmoxApi->cluster()->createSubnet($vnetName, [
                        'subnet' => $networkCidr,
                        'type' => 'subnet',
                    ]);

                    $this->proxmoxApi->cluster()->applySdn();
                }

                $this->s3Service->createForTenant(
                    tenant: $tenant,
                    bucket: 'dbaas-backups',
                    prefix: $tenant->slug . '/',
                    description: 'Default backup credential',
                );

                return $tenant;
            });
        } catch (Throwable $e) {
            return redirect()->route('tenants.create')
                ->withInput()
                ->withErrors(['error' => 'テナント作成に失敗しました: ' . $e->getMessage()]);
        }

        return redirect()->route('tenants.show', $tenant)
            ->with('success', 'テナントを作成しました。');
    }
}
