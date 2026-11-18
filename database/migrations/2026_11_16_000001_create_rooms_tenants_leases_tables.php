<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->increments('id');
            $table->string('room_number', 20)->unique('uq_rooms_number');
            $table->enum('room_type', ['single', 'double', 'dormitory']);
            $table->decimal('monthly_rate', 10, 2);
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->enum('status', ['available', 'occupied', 'maintenance'])->default('available');
            $table->unsignedTinyInteger('floor_no')->default(1);
            $table->string('description', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index('status', 'idx_rooms_status');
            $table->index('room_type', 'idx_rooms_type');
        });
        DB::statement('ALTER TABLE rooms ADD CONSTRAINT chk_rooms_rate CHECK (monthly_rate >= 0)');
        DB::statement('ALTER TABLE rooms ADD CONSTRAINT chk_rooms_capacity CHECK (capacity >= 1)');

        // 1 user : 1 tenant profile
        Schema::create('tenants', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique('uq_tenants_user');
            $table->date('birth_date')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('id_type', 50)->nullable();
            $table->string('emergency_name', 120)->nullable();
            $table->string('emergency_phone', 30)->nullable();
            $table->string('emergency_relation', 50)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->foreign('user_id', 'fk_tenants_user')->references('id')->on('users')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('leases', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('tenant_id');
            $table->unsignedInteger('room_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('monthly_rent', 10, 2);                 // snapshot of the rate at signing
            $table->decimal('security_deposit', 10, 2)->default(0);
            $table->enum('deposit_status', ['unpaid', 'held', 'refunded', 'forfeited'])->default('unpaid');
            $table->unsignedTinyInteger('billing_day')->default(1);  // day of month the invoice is due
            $table->enum('status', ['active', 'expired', 'terminated', 'pending'])->default('active');
            $table->date('move_in_date')->nullable();
            $table->date('move_out_date')->nullable();
            $table->text('agreement_notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('tenant_id', 'idx_leases_tenant');
            $table->index('room_id', 'idx_leases_room');
            $table->index(['status', 'end_date'], 'idx_leases_status_end');
            $table->foreign('tenant_id', 'fk_leases_tenant')->references('id')->on('tenants')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('room_id', 'fk_leases_room')->references('id')->on('rooms')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by', 'fk_leases_creator')->references('id')->on('users')
                ->nullOnDelete()->cascadeOnUpdate();
        });
        DB::statement('ALTER TABLE leases ADD CONSTRAINT chk_leases_dates CHECK (end_date > start_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('leases');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('rooms');
    }
};
