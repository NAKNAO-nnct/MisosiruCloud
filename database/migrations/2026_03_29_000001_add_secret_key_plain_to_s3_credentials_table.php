<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('s3_credentials', function (Blueprint $table): void {
            $table->text('secret_key_plain')->nullable()->after('secret_key_encrypted');
        });
    }

    public function down(): void
    {
        Schema::table('s3_credentials', function (Blueprint $table): void {
            $table->dropColumn('secret_key_plain');
        });
    }
};
