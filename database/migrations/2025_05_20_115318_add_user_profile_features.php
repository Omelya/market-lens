<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('timezone')->default('UTC')->after('email_verified_at');
            $table->json('notification_preferences')->nullable()->after('timezone');
            $table->timestamp('last_login_at')->nullable()->after('notification_preferences');
            $table->string('last_login_ip')->nullable()->after('last_login_at');
        });

        Schema::create('email_verification_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token')->unique();
            $table->string('new_email');
            $table->timestamps();
            $table->timestamp('expires_at')->nullable();
        });

        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('action');
            $table->string('entity_type')->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'action']);
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('exchange_api_key_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_api_key_id')->constrained()->onDelete('cascade');
            $table->string('action');
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index('exchange_api_key_id');
            $table->index('action');
        });

        Schema::table('exchange_api_keys', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('last_used_at');
            $table->json('permissions_data')->nullable()->after('permissions');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('exchange_api_keys', function (Blueprint $table) {
            $table->dropColumn(['verified_at', 'permissions_data']);
            $table->dropSoftDeletes();
        });

        Schema::dropIfExists('exchange_api_key_logs');
        Schema::dropIfExists('user_activity_logs');
        Schema::dropIfExists('email_verification_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'timezone',
                'notification_preferences',
                'last_login_at',
                'last_login_ip',
            ]);
        });
    }
};
