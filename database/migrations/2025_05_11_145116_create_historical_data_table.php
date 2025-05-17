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
        Schema::create('historical_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_pair_id')->constrained()->onDelete('cascade');
            $table->string('timeframe');
            $table->timestamp('timestamp');
            $table->decimal('open', 24, 12);
            $table->decimal('high', 24, 12);
            $table->decimal('low', 24, 12);
            $table->decimal('close', 24, 12);
            $table->decimal('volume', 36, 12);
            $table->timestamps();

            $table->unique(['trading_pair_id', 'timeframe', 'timestamp']);

            $table->index(['trading_pair_id', 'timeframe', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historical_data');
    }
};
