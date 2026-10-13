<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_admin')->default(false);
        });
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->uuid('request_key');
            $table->char('payload_hash', 64);
            $table->string('status')->default('completed');
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('discount')->default(0);
            $table->unsignedBigInteger('shipping')->default(0);
            $table->unsignedBigInteger('total');
            $table->string('coupon')->nullable();
            $table->json('address');
            $table->json('items');
            $table->timestamps();
            $table->unique(['user_id', 'request_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin'));
    }
};
