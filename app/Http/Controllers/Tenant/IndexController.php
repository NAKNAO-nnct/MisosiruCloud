<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tenants = Tenant::query()
            ->when($request->search, fn ($q, $search) => $q->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('tenants.index', compact('tenants'));
    }
}
