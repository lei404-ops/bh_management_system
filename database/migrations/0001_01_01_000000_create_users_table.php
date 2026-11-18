<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BHMS users (same columns as the original schema.sql) + login throttling + Laravel sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('full_name', 120);
            $table->string('email', 150)->unique('uq_users_email');
            $table->string('password_hash', 255);
            $table->enum('role', ['admin', 'landlord', 'tenant', 'staff'])->default('tenant');
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index('role', 'idx_users_role');
        });

        // Login throttling (brute-force protection)
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->increments('id');
            $table->string('email', 150);
            $table->string('ip_address', 45);
            $table->timestamp('attempted_at')->useCurrent();
            $table->index(['email', 'ip_address', 'attempted_at'], 'idx_attempts_lookup');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('users');
    }
};
