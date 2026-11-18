<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Db;
use App\Support\Format;
use DomainException;
use InvalidArgumentException;

/**
 * Payments: tenants submit a payment (with receipt image) -> status 'pending';
 * admin/landlord confirms or rejects it. Confirming updates the invoice (partial / paid).
 * Cash payments taken in person can be recorded and confirmed in one step by staff of the house.
 */
final class PaymentService
{
    public const METHODS = ['cash', 'bank_transfer', 'gcash', 'maya', 'mock_gateway'];

    /** Invoices visible to a user (tenant sees own, admin/landlord see all). */
    public static function invoicesFor(array $user): array
    {
        BillingService::markOverdue();
        $scope  = '';
        $params = [];
        if ($user['role'] === 'tenant') {
            $scope    = 'WHERE t.user_id = ?';
            $params[] = $user['id'];
        }
        return Db::fetchAll(
            "SELECT i.id, i.invoice_no, i.billing_month, i.due_date, i.total_amount, i.amount_paid,
                    (i.total_amount - i.amount_paid) AS balance, i.status,
                    r.room_number, u.full_name AS tenant_name
             FROM invoices i
             JOIN leases  l ON l.id = i.lease_id
             JOIN rooms   r ON r.id = l.room_id
             JOIN tenants t ON t.id = l.tenant_id
             JOIN users   u ON u.id = t.user_id
             $scope
             ORDER BY i.billing_month DESC, i.id DESC LIMIT 100",
            $params
        );
    }

    /** Payment history (tenant: own payments; managers: latest 50 across all tenants). */
    public static function historyFor(array $user): array
    {
        $scope  = '';
        $params = [];
        if ($user['role'] === 'tenant') {
            $scope    = 'WHERE t.user_id = ?';
            $params[] = $user['id'];
        }
        return Db::fetchAll(
            "SELECT p.id, p.receipt_no, p.amount, p.method, p.status, p.paid_at, i.invoice_no, u.full_name AS tenant_name
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN leases l ON l.id = i.lease_id
             JOIN tenants t ON t.id = l.tenant_id
             JOIN users u ON u.id = t.user_id
             $scope ORDER BY p.paid_at DESC, p.id DESC LIMIT 50",
            $params
        );
    }

    /**
     * @param bool $confirmNow true only for admin/landlord recording cash they just received
     */
    public static function submit(array $user, int $invoiceId, float $amount, string $method, ?string $reference,
                                  ?string $receiptImage, bool $confirmNow = false): array
    {
        if (!in_array($method, self::METHODS, true)) {
            throw new InvalidArgumentException('Unknown payment method.');
        }
        $invoice = Db::fetchOne(
            'SELECT i.*, t.user_id AS tenant_user_id FROM invoices i
             JOIN leases l ON l.id = i.lease_id JOIN tenants t ON t.id = l.tenant_id WHERE i.id = ?',
            [$invoiceId]
        );
        // Tenants may only pay their own invoices (IDOR protection).
        if (!$invoice || ($user['role'] === 'tenant' && (int) $invoice['tenant_user_id'] !== (int) $user['id'])) {
            throw new DomainException('Invoice not found.');
        }
        if (in_array($invoice['status'], ['paid', 'cancelled'], true)) {
            throw new DomainException('This invoice is already settled or cancelled.');
        }
        $balance = round((float) $invoice['total_amount'] - (float) $invoice['amount_paid'], 2);
        if ($amount <= 0 || $amount > $balance + 0.001) {
            throw new InvalidArgumentException('Amount must be between 0.01 and the outstanding balance of ' . Format::money($balance) . '.');
        }
        if ($method !== 'cash' && $method !== 'mock_gateway' && !$reference && !$receiptImage) {
            throw new InvalidArgumentException('Please provide a reference number or upload your receipt.');
        }

        return Db::transaction(function () use ($user, $invoice, $amount, $method, $reference, $receiptImage, $confirmNow) {
            Db::execute(
                'INSERT INTO payments (receipt_no, invoice_id, amount, method, reference_no, receipt_image, paid_at, submitted_by)
                 VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)',
                ['TMP' . bin2hex(random_bytes(6)), $invoice['id'], $amount, $method, $reference, $receiptImage, $user['id']]
            );
            $id        = Db::lastInsertId();
            $receiptNo = 'RCP-' . date('Ym') . '-' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
            Db::execute('UPDATE payments SET receipt_no = ? WHERE id = ?', [$receiptNo, $id]);

            if ($confirmNow) {
                self::decide($id, true, $user, 'Recorded in person');
            } else {
                NotificationService::sendToRoles(['admin', 'landlord'], 'payment_submitted',
                    "Payment $receiptNo (" . Format::money($amount) . ") awaits verification.", 'dashboard');
            }
            return ['id' => $id, 'receipt_no' => $receiptNo];
        });
    }

    /** Admin/landlord approves or rejects a pending payment. */
    public static function decide(int $paymentId, bool $approve, array $user, string $remarks = ''): void
    {
        if (!in_array($user['role'], ['admin', 'landlord'], true)) {
            throw new DomainException('Only the admin or landlord can verify payments.');
        }
        Db::transaction(function () use ($paymentId, $approve, $user, $remarks) {
            // FOR UPDATE locks the row so two admins cannot confirm the same payment twice.
            $p = Db::fetchOne('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
            if (!$p || $p['status'] !== 'pending') {
                throw new DomainException('Payment not found or already processed.');
            }
            Db::execute(
                'UPDATE payments SET status = ?, verified_by = ?, verified_at = NOW(), remarks = ? WHERE id = ?',
                [$approve ? 'confirmed' : 'rejected', $user['id'], $remarks ?: null, $paymentId]
            );
            if ($approve) {
                self::refreshInvoice((int) $p['invoice_id']);
            }
            $owner = Db::fetchValue(
                'SELECT t.user_id FROM invoices i JOIN leases l ON l.id = i.lease_id JOIN tenants t ON t.id = l.tenant_id WHERE i.id = ?',
                [$p['invoice_id']]
            );
            if ($owner) {
                NotificationService::send((int) $owner, 'payment_' . ($approve ? 'confirmed' : 'rejected'),
                    "Payment {$p['receipt_no']} was " . ($approve ? 'confirmed. Thank you!' : 'rejected. ' . $remarks), 'dashboard');
            }
        });
    }

    /** Recompute amount_paid and status from confirmed payments (single source of truth). */
    private static function refreshInvoice(int $invoiceId): void
    {
        $paid = (float) Db::fetchValue(
            "SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id = ? AND status = 'confirmed'", [$invoiceId]);
        $inv  = Db::fetchOne('SELECT total_amount, due_date FROM invoices WHERE id = ?', [$invoiceId]);
        $total = (float) $inv['total_amount'];

        $status = 'pending';
        if ($paid + 0.001 >= $total) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partial';
        }
        if ($status !== 'paid' && $inv['due_date'] < date('Y-m-d')) {
            $status = 'overdue';
        }
        Db::execute('UPDATE invoices SET amount_paid = ?, status = ? WHERE id = ?', [$paid, $status, $invoiceId]);
    }

    public static function pending(): array
    {
        return Db::fetchAll(
            "SELECT p.id, p.receipt_no, p.amount, p.method, p.reference_no, (p.receipt_image IS NOT NULL) AS has_receipt,
                    p.paid_at, i.invoice_no, u.full_name AS tenant_name
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN leases l ON l.id = i.lease_id
             JOIN tenants t ON t.id = l.tenant_id
             JOIN users u ON u.id = t.user_id
             WHERE p.status = 'pending' ORDER BY p.paid_at"
        );
    }

    /** Full data for the printable receipt; enforces ownership for tenants. */
    public static function receipt(int $paymentId, array $user): ?array
    {
        $row = Db::fetchOne(
            "SELECT p.*, i.invoice_no, i.billing_month, i.total_amount, i.amount_paid,
                    r.room_number, u.full_name AS tenant_name, u.id AS tenant_user_id,
                    vu.full_name AS verified_name
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN leases l ON l.id = i.lease_id
             JOIN rooms r ON r.id = l.room_id
             JOIN tenants t ON t.id = l.tenant_id
             JOIN users u ON u.id = t.user_id
             LEFT JOIN users vu ON vu.id = p.verified_by
             WHERE p.id = ?",
            [$paymentId]
        );
        if (!$row) {
            return null;
        }
        if ($user['role'] === 'tenant' && (int) $row['tenant_user_id'] !== (int) $user['id']) {
            return null;
        }
        if (in_array($user['role'], ['staff'], true)) {
            return null;
        }
        return $row;
    }
}
