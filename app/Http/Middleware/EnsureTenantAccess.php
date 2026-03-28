<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->route('tenant');

        if (!$tenant instanceof Tenant) {
            return $next($request);
        }

        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $isMember = $user->tenants()->where('tenants.id', $tenant->id)->exists();

        if (!$isMember) {
            abort(403);
        }

        return $next($request);
    }
}
