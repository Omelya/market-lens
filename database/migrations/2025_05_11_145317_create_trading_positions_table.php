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
        Schema::create('trading_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('api_key_id')->constrained()->onDelete('cascade');
            $table->foreignId('trading_pair_id')->constrained()->onDelete('cascade');
            $table->foreignId('trading_signal_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('position_type', ['manual', 'auto']);
            $table->enum('direction', ['long', 'short']);
            $table->enum('status', ['open', 'closed', 'canceled', 'error']);

            $table->decimal('entry_price', 24, 12);
            $table->decimal('size', 24, 12);
            $table->decimal('leverage', 10, 2)->default(1);
            $table->string('entry_order_id')->nullable();
            $table->timestamp('opened_at');

            $table->decimal('exit_price', 24, 12)->nullable();
            $table->string('exit_order_id')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->decimal('stop_loss', 24, 12)->nullable();
            $table->string('stop_loss_order_id')->nullable();
            $table->decimal('take_profit', 24, 12)->nullable();
            $table->string('take_profit_order_id')->nullable();
            $table->boolean('trailing_stop')->default(false);
            $table->decimal('trailing_stop_distance', 24, 12)->nullable();

            $table->decimal('realized_pnl', 24, 12)->nullable();
            $table->decimal('fee', 24, 12)->nullable();
            $table->enum('result', ['profit', 'loss', 'breakeven', 'unknown'])->nullable();

            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['trading_pair_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_positions');
    }
};
