<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('proxmox_nodes', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('hostname');
            $table->string('api_token_id');
            $table->text('api_token_secret_encrypted');
            $table->string('snippet_api_url');
            $table->text('snippet_api_token_encrypted');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxmox_nodes');
    }
};
