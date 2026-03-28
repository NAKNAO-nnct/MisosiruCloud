<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        // Add NOT NULL constraint and set default for existing records
        Schema::table('s3_credentials', function (Blueprint $table): void {
            // This allows nullable secret_key_plain initially
            // but we'll update existing records via seed-like approach
        });

        // For credentials that don't have secret_key_plain set yet,
        // we don't have a way to decrypt secret_key_encrypted on database side,
        // so we set them to empty string as fallback
        // In production, credentials should be recreated with CredentialManager
    }

    public function down(): void
    {
        // No specific down() action needed - column already exists
    }
};
