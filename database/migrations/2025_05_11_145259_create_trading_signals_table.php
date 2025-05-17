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
        Schema::create('trading_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_pair_id')->constrained()->onDelete('cascade');
            $table->string('timeframe');
            $table->timestamp('timestamp');
            $table->enum('direction', ['buy', 'sell', 'neutral']);
            $table->enum('signal_type', ['technical', 'ml', 'combined']);
            $table->enum('strength', ['weak', 'medium', 'strong']);
            $table->decimal('entry_price', 24, 12);
            $table->decimal('stop_loss', 24, 12)->nullable();
            $table->decimal('take_profit', 24, 12)->nullable();
            $table->json('indicators_data')->nullable();
            $table->decimal('risk_reward_ratio', 10, 2)->nullable();
            $table->decimal('success_probability', 5, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['trading_pair_id', 'timeframe', 'timestamp', 'direction']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_signals');
    }
};
