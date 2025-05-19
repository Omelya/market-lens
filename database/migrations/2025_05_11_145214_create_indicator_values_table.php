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
        Schema::create('indicator_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('technical_indicator_id')->constrained()->onDelete('cascade');
            $table->foreignId('trading_pair_id')->constrained()->onDelete('cascade');
            $table->string('timeframe');
            $table->timestamp('timestamp');
            $table->json('parameters');
            $table->json('values');
            $table->timestamps();

            $table->unique(['technical_indicator_id', 'trading_pair_id', 'timeframe', 'timestamp'], 'indicator_unique');

            $table->index(['trading_pair_id', 'timeframe', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_values');
    }
};
