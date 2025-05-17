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
        Schema::create('trading_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_id')->constrained()->onDelete('cascade');
            $table->string('symbol');
            $table->string('base_currency');
            $table->string('quote_currency');
            $table->decimal('min_order_size', 24, 12)->nullable();
            $table->decimal('max_order_size', 24, 12)->nullable();
            $table->decimal('price_precision', 24, 12)->nullable();
            $table->decimal('size_precision', 24, 12)->nullable();
            $table->decimal('maker_fee', 10, 6)->nullable();
            $table->decimal('taker_fee', 10, 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['exchange_id', 'symbol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_pairs');
    }
};
