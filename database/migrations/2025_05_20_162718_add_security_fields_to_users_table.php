<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('security_settings')->nullable()->after('notification_preferences');
        });

        Schema::create('user_security_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('event_type');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('details')->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->string('device_id')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'event_type']);
            $table->index(['user_id', 'is_suspicious']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_security_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('security_settings');
        });
    }
};
