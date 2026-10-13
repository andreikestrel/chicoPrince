<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->char('import_key', 64)->nullable()->unique();
            $table->text('source_url')->nullable();
            $table->boolean('demo_variants')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['import_key']);
            $table->dropColumn(['import_key', 'source_url', 'demo_variants']);
        });
    }
};
