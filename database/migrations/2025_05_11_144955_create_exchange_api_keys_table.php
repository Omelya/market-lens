<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exchange_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('exchange_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('api_key');
            $table->text('api_secret')->nullable();
            $table->text('passphrase')->nullable();
            $table->boolean('is_test_net')->default(false);
            $table->boolean('trading_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exchange_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_api_keys');
    }
};
