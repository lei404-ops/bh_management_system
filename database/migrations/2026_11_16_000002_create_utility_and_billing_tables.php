<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_rates', function (Blueprint $table) {
            $table->increments('id');
            $table->enum('utility_type', ['water', 'electricity', 'internet']);
            $table->decimal('rate_per_unit', 10, 4);        // internet is a flat fee per month
            $table->string('unit_label', 20);               // m3, kWh, month
            $table->date('effective_from');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['utility_type', 'effective_from'], 'idx_rates_type_date');
        });

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('room_id');
            $table->enum('utility_type', ['water', 'electricity']);
            $table->date('billing_month');                  // always the first day of the month
            $table->decimal('previous_reading', 10, 2);
            $table->decimal('current_reading', 10, 2);
            $table->decimal('consumption', 10, 2)->storedAs('current_reading - previous_reading');
            $table->decimal('rate_used', 10, 4);
            $table->decimal('amount', 10, 2);
            $table->unsignedInteger('recorded_by')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
            $table->unique(['room_id', 'utility_type', 'billing_month'], 'uq_reading_room_type_month');
            $table->foreign('room_id', 'fk_readings_room')->references('id')->on('rooms')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('recorded_by', 'fk_readings_user')->references('id')->on('users')
                ->nullOnDelete()->cascadeOnUpdate();
        });
        DB::statement('ALTER TABLE meter_readings ADD CONSTRAINT chk_reading_order CHECK (current_reading >= previous_reading)');

        Schema::create('invoices', function (Blueprint $table) {
            $table->increments('id');
            $table->string('invoice_no', 30)->unique('uq_invoice_no');
            $table->unsignedInteger('lease_id');
            $table->date('billing_month');
            $table->date('due_date');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->enum('status', ['pending', 'partial', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->timestamp('created_at')->useCurrent();
            // One invoice per lease per month: running billing twice never double-bills.
            $table->unique(['lease_id', 'billing_month'], 'uq_invoice_lease_month');
            $table->index(['status', 'due_date'], 'idx_invoice_status_due');
            $table->foreign('lease_id', 'fk_invoices_lease')->references('id')->on('leases')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('invoice_id')->index('idx_items_invoice');
            $table->enum('item_type', ['rent', 'water', 'electricity', 'internet', 'deposit', 'other']);
            $table->string('description', 150);
            $table->decimal('amount', 10, 2);
            $table->foreign('invoice_id', 'fk_items_invoice')->references('id')->on('invoices')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->increments('id');
            $table->string('receipt_no', 30)->unique('uq_receipt_no');
            $table->unsignedInteger('invoice_id');
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['cash', 'bank_transfer', 'gcash', 'maya', 'mock_gateway']);
            $table->string('reference_no', 80)->nullable();
            $table->string('receipt_image', 255)->nullable();   // stored file name only
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->dateTime('paid_at');
            $table->unsignedInteger('submitted_by')->nullable();
            $table->unsignedInteger('verified_by')->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->index('invoice_id', 'idx_payments_invoice');
            $table->index('status', 'idx_payments_status');
            $table->index('paid_at', 'idx_payments_paid_at');
            $table->foreign('invoice_id', 'fk_payments_invoice')->references('id')->on('invoices')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('submitted_by', 'fk_payments_submitter')->references('id')->on('users')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('verified_by', 'fk_payments_verifier')->references('id')->on('users')
                ->nullOnDelete()->cascadeOnUpdate();
        });
        DB::statement('ALTER TABLE payments ADD CONSTRAINT chk_payments_amount CHECK (amount > 0)');

        // Report view: monthly revenue (confirmed payments only).
        DB::statement("CREATE OR REPLACE VIEW v_monthly_revenue AS
            SELECT DATE_FORMAT(paid_at, '%Y-%m') AS month_key,
                   SUM(amount)                  AS revenue,
                   COUNT(*)                     AS payment_count
            FROM payments
            WHERE status = 'confirmed'
            GROUP BY DATE_FORMAT(paid_at, '%Y-%m')");
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_monthly_revenue');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('utility_rates');
    }
};
