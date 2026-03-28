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
            // Rename 'description' to 'name' at the same position
            // Since Laravel doesn't support direct rename with position,
            // we'll add 'name' and deprecate 'description'
            if (!Schema::hasColumn('s3_credentials', 'name')) {
                $table->string('name')->nullable()->after('tenant_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('s3_credentials', function (Blueprint $table): void {
            if (Schema::hasColumn('s3_credentials', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
