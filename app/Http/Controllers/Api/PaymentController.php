<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use App\Services\UploadService;
use App\Support\ApiResponse;
use App\Support\Validator;
use Illuminate\Http\Request;

/**
 * Payments API (admin, landlord, tenant - enforced by the route middleware)
 *   GET  /api/payments                    invoices (tenant: own, managers: all)
 *   GET  /api/payments?pending=1          payments awaiting verification (admin/landlord)
 *   GET  /api/payments?history=1          payment history
 *   POST /api/payments action=submit      invoice_id, amount, method, reference_no, receipt (file)
 *   POST /api/payments action=decide      payment_id, decision=confirm|reject, remarks (admin/landlord)
 */
class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user      = $request->user()->asArray();
        $isManager = in_array($user['role'], ['admin', 'landlord'], true);

        if ($request->query->has('pending')) {
            if (!$isManager) {
                return ApiResponse::error('You do not have permission to do that.', 403);
            }
            return ApiResponse::ok(PaymentService::pending());
        }
        if ($request->query->has('history')) {
            return ApiResponse::ok(PaymentService::historyFor($user));
        }
        return ApiResponse::ok(PaymentService::invoicesFor($user));
    }

    public function store(Request $request)
    {
        $user      = $request->user()->asArray();
        $isManager = in_array($user['role'], ['admin', 'landlord'], true);
        $v         = new Validator($request->all());

        switch ((string) $request->input('action', '')) {
            case 'submit':
                $v->positiveInt('invoice_id', 'Invoice')->money('amount', 'Amount')
                  ->inList('method', PaymentService::METHODS, 'Payment method')->maxLength('reference_no', 80, 'Reference number');
                if ($v->fails()) {
                    return ApiResponse::error('Please fix the highlighted fields.', 422, $v->errors());
                }
                $receipt = UploadService::image($request->file('receipt'), 'receipts');
                // Only managers may mark a payment as confirmed immediately (cash received in person).
                $confirmNow = $isManager && (string) $request->input('confirm_now', '') === '1';
                $res = PaymentService::submit($user, (int) $v->get('invoice_id'), (float) $v->get('amount'),
                    $v->get('method'), $v->get('reference_no') ?: null, $receipt, $confirmNow);
                return ApiResponse::ok($res, $confirmNow ? 'Payment recorded.' : 'Payment submitted. It will be confirmed once verified.');

            case 'decide':
                $v->positiveInt('payment_id', 'Payment')->inList('decision', ['confirm', 'reject'], 'Decision')
                  ->maxLength('remarks', 255, 'Remarks');
                if ($v->fails()) {
                    return ApiResponse::error('Invalid request.', 422, $v->errors());
                }
                PaymentService::decide((int) $v->get('payment_id'), $v->get('decision') === 'confirm', $user, $v->get('remarks'));
                return ApiResponse::ok(null, 'Payment ' . ($v->get('decision') === 'confirm' ? 'confirmed.' : 'rejected.'));

            default:
                return ApiResponse::error('Unknown action.', 400);
        }
    }
}
