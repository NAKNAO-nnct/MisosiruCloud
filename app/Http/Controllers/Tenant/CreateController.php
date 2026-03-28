<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreateController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('tenants.create');
    }
}
