<?php

declare(strict_types=1);

namespace App\Http\Controllers\S3Credential;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndexController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant): View
    {
        $credentials = $tenant->s3Credentials()->orderByDesc('created_at')->paginate(20);

        return view('s3_credentials.index', compact('tenant', 'credentials'));
    }
}
