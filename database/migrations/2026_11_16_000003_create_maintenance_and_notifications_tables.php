<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('ticket_no', 20)->unique('uq_ticket_no');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('room_id');
            $table->enum('category', ['plumbing', 'electrical', 'carpentry', 'appliance', 'internet', 'other']);
            $table->string('title', 150);
            $table->text('description');
            $table->string('photo', 255)->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', ['pending', 'in_progress', 'resolved', 'cancelled'])->default('pending');
            $table->unsignedInteger('assigned_to')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index('status', 'idx_mr_status');
            $table->index('tenant_id', 'idx_mr_tenant');
            $table->index('assigned_to', 'idx_mr_assigned');
            $table->foreign('tenant_id', 'fk_mr_tenant')->references('id')->on('tenants')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('room_id', 'fk_mr_room')->references('id')->on('rooms')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('assigned_to', 'fk_mr_staff')->references('id')->on('users')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('maintenance_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('request_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->string('action', 40);                    // created, assigned, status_changed, note
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->string('note', 500)->nullable();
            $table->timestamp('logged_at')->useCurrent();
            $table->index(['request_id', 'logged_at'], 'idx_logs_request');
            $table->foreign('request_id', 'fk_logs_request')->references('id')->on('maintenance_requests')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id', 'fk_logs_user')->references('id')->on('users')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        // In-app notifications (own table, not Laravel's built-in notifications table).
        Schema::create('notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('type', 40);
            $table->string('message', 255);
            $table->string('link', 255)->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'is_read'], 'idx_notif_user_read');
            $table->foreign('user_id', 'fk_notif_user')->references('id')->on('users')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('maintenance_logs');
        Schema::dropIfExists('maintenance_requests');
    }
};
