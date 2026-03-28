<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Lib\Proxmox\ProxmoxApi;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class DestroyController extends Controller
{
    public function __construct(private readonly ?ProxmoxApi $proxmoxApi)
    {
    }

    public function __invoke(Request $request, Tenant $tenant): RedirectResponse
    {
        if ($this->proxmoxApi && $tenant->vnet_name) {
            try {
                $this->proxmoxApi->cluster()->deleteVnet($tenant->vnet_name);
                $this->proxmoxApi->cluster()->applySdn();
            } catch (Throwable) {
                // SDN削除失敗でもテナント状態変更は続行
            }
        }

        $tenant->update(['status' => TenantStatus::Deleted]);

        return redirect()->route('tenants.index')
            ->with('success', 'テナントを削除しました。');
    }
}
