<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->text('source_instance_migration_api_token')->nullable();
            $table->boolean('migration_enabled')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('instance_settings', function (Blueprint $table) {
            $table->dropColumn('source_instance_migration_api_token');
            $table->dropColumn('migration_enabled');
        });
    }
};
