<?php

declare(strict_types=1);

namespace App\Http\Controllers\S3Credential;

use App\Http\Controllers\Controller;
use App\Models\S3Credential;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShowController extends Controller
{
    public function __invoke(Request $request, Tenant $tenant, S3Credential $s3Credential): View
    {
        abort_unless($s3Credential->tenant_id === $tenant->id, 404);

        return view('s3_credentials.show', compact('tenant', 's3Credential'));
    }
}
