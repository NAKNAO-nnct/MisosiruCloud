<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('s3_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('access_key', 64)->unique();
            $table->text('secret_key_encrypted');
            $table->string('allowed_bucket');
            $table->string('allowed_prefix');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('s3_credentials');
    }
};
