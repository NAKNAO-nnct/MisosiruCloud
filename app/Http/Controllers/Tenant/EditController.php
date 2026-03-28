<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EditController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): View
    {
        return view('tenants.edit', compact('tenant'));
    }
}
