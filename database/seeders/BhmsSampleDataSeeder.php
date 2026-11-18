<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Sample data from the original BHMS schema.sql.
 * Every sample user signs in with the password:  Password@123
 *   admin@bhms.test | landlord@bhms.test | tenant1@bhms.test | tenant2@bhms.test | staff@bhms.test
 */
class BhmsSampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $hash = Hash::make('Password@123');

        DB::table('users')->insert([
            ['id' => 1, 'full_name' => 'Alice Admin',  'email' => 'admin@bhms.test',    'password_hash' => $hash, 'role' => 'admin',    'phone' => '0917-000-0001'],
            ['id' => 2, 'full_name' => 'Leo Landlord', 'email' => 'landlord@bhms.test', 'password_hash' => $hash, 'role' => 'landlord', 'phone' => '0917-000-0002'],
            ['id' => 3, 'full_name' => 'Tina Tenant',  'email' => 'tenant1@bhms.test',  'password_hash' => $hash, 'role' => 'tenant',   'phone' => '0917-000-0003'],
            ['id' => 4, 'full_name' => 'Tom Tenant',   'email' => 'tenant2@bhms.test',  'password_hash' => $hash, 'role' => 'tenant',   'phone' => '0917-000-0004'],
            ['id' => 5, 'full_name' => 'Sam Staff',    'email' => 'staff@bhms.test',    'password_hash' => $hash, 'role' => 'staff',    'phone' => '0917-000-0005'],
        ]);

        DB::table('rooms')->insert([
            ['id' => 1, 'room_number' => 'S-101',  'room_type' => 'single',    'monthly_rate' => 3500, 'capacity' => 1, 'status' => 'occupied',    'floor_no' => 1, 'description' => 'Single room with own fan and window'],
            ['id' => 2, 'room_number' => 'S-102',  'room_type' => 'single',    'monthly_rate' => 3500, 'capacity' => 1, 'status' => 'available',   'floor_no' => 1, 'description' => 'Single room near the common kitchen'],
            ['id' => 3, 'room_number' => 'D-201',  'room_type' => 'double',    'monthly_rate' => 5500, 'capacity' => 2, 'status' => 'occupied',    'floor_no' => 2, 'description' => 'Double room, shared bathroom'],
            ['id' => 4, 'room_number' => 'D-202',  'room_type' => 'double',    'monthly_rate' => 5500, 'capacity' => 2, 'status' => 'maintenance', 'floor_no' => 2, 'description' => 'Double room - ceiling repair ongoing'],
            ['id' => 5, 'room_number' => 'DM-301', 'room_type' => 'dormitory', 'monthly_rate' => 2000, 'capacity' => 6, 'status' => 'available',   'floor_no' => 3, 'description' => 'Per-bed rate, 6-bed dorm'],
            ['id' => 6, 'room_number' => 'S-103',  'room_type' => 'single',    'monthly_rate' => 3800, 'capacity' => 1, 'status' => 'available',   'floor_no' => 1, 'description' => 'Single room with private bathroom'],
        ]);

        DB::table('tenants')->insert([
            ['id' => 1, 'user_id' => 3, 'birth_date' => '2003-05-14', 'address' => 'Brgy. San Roque, Cebu',  'occupation' => 'College student',   'id_type' => 'Student ID',     'emergency_name' => 'Maria Tenant', 'emergency_phone' => '0918-111-2222', 'emergency_relation' => 'Mother'],
            ['id' => 2, 'user_id' => 4, 'birth_date' => '1999-11-02', 'address' => 'Brgy. Poblacion, Davao', 'occupation' => 'Call-center agent', 'id_type' => 'Driver License', 'emergency_name' => 'Pedro Tenant', 'emergency_phone' => '0918-333-4444', 'emergency_relation' => 'Father'],
        ]);

        DB::table('leases')->insert([
            // Lease 1 ends within 30 days: demo for the expiry alerts.
            ['id' => 1, 'tenant_id' => 1, 'room_id' => 1, 'start_date' => '2026-06-01', 'end_date' => '2026-11-25', 'monthly_rent' => 3500, 'security_deposit' => 3500, 'deposit_status' => 'held', 'billing_day' => 5, 'status' => 'active', 'move_in_date' => '2026-06-01', 'created_by' => 2],
            ['id' => 2, 'tenant_id' => 2, 'room_id' => 3, 'start_date' => '2026-08-01', 'end_date' => '2027-07-31', 'monthly_rent' => 5500, 'security_deposit' => 5500, 'deposit_status' => 'held', 'billing_day' => 5, 'status' => 'active', 'move_in_date' => '2026-08-01', 'created_by' => 2],
        ]);

        DB::table('utility_rates')->insert([
            ['utility_type' => 'water',       'rate_per_unit' => 35,  'unit_label' => 'm3',    'effective_from' => '2026-01-01'],
            ['utility_type' => 'electricity', 'rate_per_unit' => 14.5, 'unit_label' => 'kWh',  'effective_from' => '2026-01-01'],
            ['utility_type' => 'internet',    'rate_per_unit' => 300, 'unit_label' => 'month', 'effective_from' => '2026-01-01'],
        ]);

        DB::table('meter_readings')->insert([
            ['room_id' => 1, 'utility_type' => 'water',       'billing_month' => '2026-10-01', 'previous_reading' => 120,  'current_reading' => 126,  'rate_used' => 35,   'amount' => 210,    'recorded_by' => 2],
            ['room_id' => 1, 'utility_type' => 'electricity', 'billing_month' => '2026-10-01', 'previous_reading' => 840,  'current_reading' => 905,  'rate_used' => 14.5, 'amount' => 942.5,  'recorded_by' => 2],
            ['room_id' => 3, 'utility_type' => 'water',       'billing_month' => '2026-10-01', 'previous_reading' => 300,  'current_reading' => 311,  'rate_used' => 35,   'amount' => 385,    'recorded_by' => 2],
            ['room_id' => 3, 'utility_type' => 'electricity', 'billing_month' => '2026-10-01', 'previous_reading' => 1500, 'current_reading' => 1620, 'rate_used' => 14.5, 'amount' => 1740,   'recorded_by' => 2],
        ]);

        DB::table('invoices')->insert([
            ['id' => 1, 'invoice_no' => 'INV-202609-0001', 'lease_id' => 1, 'billing_month' => '2026-09-01', 'due_date' => '2026-09-05', 'total_amount' => 4350,   'amount_paid' => 4350, 'status' => 'paid'],
            ['id' => 2, 'invoice_no' => 'INV-202609-0002', 'lease_id' => 2, 'billing_month' => '2026-09-01', 'due_date' => '2026-09-05', 'total_amount' => 6200,   'amount_paid' => 6200, 'status' => 'paid'],
            ['id' => 3, 'invoice_no' => 'INV-202610-0001', 'lease_id' => 1, 'billing_month' => '2026-10-01', 'due_date' => '2026-10-05', 'total_amount' => 4952.5, 'amount_paid' => 0,    'status' => 'overdue'],
            ['id' => 4, 'invoice_no' => 'INV-202610-0002', 'lease_id' => 2, 'billing_month' => '2026-10-01', 'due_date' => '2026-11-05', 'total_amount' => 7925,   'amount_paid' => 0,    'status' => 'pending'],
        ]);

        $items = [
            [1, 'rent', 'Room rent - Sep 2026', 3500], [1, 'internet', 'Internet - Sep 2026', 300], [1, 'water', 'Water - Sep 2026', 200], [1, 'electricity', 'Electricity - Sep 2026', 350],
            [2, 'rent', 'Room rent - Sep 2026', 5500], [2, 'internet', 'Internet - Sep 2026', 300], [2, 'water', 'Water - Sep 2026', 150], [2, 'electricity', 'Electricity - Sep 2026', 250],
            [3, 'rent', 'Room rent - Oct 2026', 3500], [3, 'internet', 'Internet - Oct 2026', 300], [3, 'water', 'Water - Oct 2026 (6 m3)', 210], [3, 'electricity', 'Electricity - Oct 2026 (65 kWh)', 942.5],
            [4, 'rent', 'Room rent - Oct 2026', 5500], [4, 'internet', 'Internet - Oct 2026', 300], [4, 'water', 'Water - Oct 2026 (11 m3)', 385], [4, 'electricity', 'Electricity - Oct 2026 (120 kWh)', 1740],
        ];
        DB::table('invoice_items')->insert(array_map(
            fn ($i) => ['invoice_id' => $i[0], 'item_type' => $i[1], 'description' => $i[2], 'amount' => $i[3]],
            $items
        ));

        DB::table('payments')->insert([
            ['receipt_no' => 'RCP-202609-0001', 'invoice_id' => 1, 'amount' => 4350, 'method' => 'gcash',         'reference_no' => 'GC-88123401', 'status' => 'confirmed', 'paid_at' => '2026-09-04 10:15:00', 'submitted_by' => 3, 'verified_by' => 2, 'verified_at' => '2026-09-04 14:00:00'],
            ['receipt_no' => 'RCP-202609-0002', 'invoice_id' => 2, 'amount' => 6200, 'method' => 'bank_transfer', 'reference_no' => 'BT-5521907',  'status' => 'confirmed', 'paid_at' => '2026-09-03 16:40:00', 'submitted_by' => 4, 'verified_by' => 2, 'verified_at' => '2026-09-04 09:00:00'],
        ]);

        DB::table('maintenance_requests')->insert([
            ['ticket_no' => 'MT-000001', 'tenant_id' => 1, 'room_id' => 1, 'category' => 'plumbing',   'title' => 'Leaking faucet',           'description' => 'The bathroom faucet keeps dripping even when fully closed.', 'priority' => 'medium', 'status' => 'pending',     'assigned_to' => null, 'created_at' => '2026-10-26 09:30:00'],
            ['ticket_no' => 'MT-000002', 'tenant_id' => 2, 'room_id' => 3, 'category' => 'electrical', 'title' => 'Flickering ceiling light', 'description' => 'The ceiling light flickers at night.',                          'priority' => 'high',   'status' => 'in_progress', 'assigned_to' => 5,    'created_at' => '2026-10-24 19:10:00'],
        ]);

        DB::table('maintenance_logs')->insert([
            ['request_id' => 1, 'user_id' => 3, 'action' => 'created',        'from_status' => null,      'to_status' => 'pending',     'note' => 'Ticket submitted by tenant'],
            ['request_id' => 2, 'user_id' => 4, 'action' => 'created',        'from_status' => null,      'to_status' => 'pending',     'note' => 'Ticket submitted by tenant'],
            ['request_id' => 2, 'user_id' => 2, 'action' => 'assigned',       'from_status' => 'pending', 'to_status' => 'pending',     'note' => 'Assigned to Sam Staff'],
            ['request_id' => 2, 'user_id' => 5, 'action' => 'status_changed', 'from_status' => 'pending', 'to_status' => 'in_progress', 'note' => 'On my way with replacement bulb/ballast'],
        ]);

        DB::table('notifications')->insert([
            ['user_id' => 2, 'type' => 'lease_expiring',  'message' => 'Lease for room S-101 (Tina Tenant) ends on 2026-11-25.', 'link' => 'rooms'],
            ['user_id' => 3, 'type' => 'invoice_overdue', 'message' => 'Invoice INV-202610-0001 is overdue.',                   'link' => 'dashboard'],
        ]);
    }
}
