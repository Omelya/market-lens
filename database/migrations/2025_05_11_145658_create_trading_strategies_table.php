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
        Schema::create('trading_strategies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(false);

            $table->json('trading_pairs')->nullable();
            $table->json('timeframes')->nullable();
            $table->json('indicators')->nullable();
            $table->json('entry_rules')->nullable();
            $table->json('exit_rules')->nullable();

            $table->decimal('risk_per_trade', 5, 2)->nullable();
            $table->decimal('max_open_positions', 5, 2)->nullable();
            $table->decimal('max_daily_drawdown', 5, 2)->nullable();

            $table->enum('execution_mode', ['manual', 'semi_auto', 'auto'])->default('manual');
            $table->boolean('notifications_enabled')->default(true);

            $table->integer('total_trades')->default(0);
            $table->integer('winning_trades')->default(0);
            $table->integer('losing_trades')->default(0);
            $table->decimal('win_rate', 5, 2)->default(0);
            $table->decimal('average_profit', 10, 2)->default(0);
            $table->decimal('average_loss', 10, 2)->default(0);
            $table->decimal('profit_factor', 10, 2)->default(0);
            $table->decimal('total_profit', 24, 12)->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_strategies');
    }
};
