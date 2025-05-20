<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_management_strategies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('risk_percentage', 5, 2)->default(1.0);
            $table->decimal('risk_reward_ratio', 5, 2)->default(2.0);
            $table->boolean('use_trailing_stop')->default(false);
            $table->decimal('trailing_stop_activation', 5, 2)->nullable();
            $table->decimal('trailing_stop_distance', 5, 2)->nullable();
            $table->decimal('max_risk_per_trade', 10, 2)->nullable();
            $table->integer('max_concurrent_trades')->nullable();
            $table->decimal('max_daily_drawdown', 5, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('parameters')->nullable();
            $table->timestamps();
        });

        // Додаємо поле risk_strategy_id до таблиці trading_positions
        Schema::table('trading_positions', function (Blueprint $table) {
            $table->foreignId('risk_strategy_id')->nullable()->after('trading_signal_id')
                ->constrained('risk_management_strategies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trading_positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risk_strategy_id');
        });

        Schema::dropIfExists('risk_management_strategies');
    }
};
