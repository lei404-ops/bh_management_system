<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use App\Services\TenantService;
use App\Support\ApiResponse;
use App\Support\Db;
use App\Support\Validator;
use DateTime;
use Illuminate\Http\Request;

/**
 * Tenants API  (admin + landlord only - enforced by the route middleware)
 *   GET  /api/tenants?q=&page=            list tenants
 *   GET  /api/tenants?rooms=1             rooms that can still take a tenant
 *   POST /api/tenants action=create       create login + profile (+ room/lease)
 *   POST /api/tenants action=active       enable/disable a tenant login (user_id, active=0|1)
 *   POST /api/tenants action=reset_password   new temporary password (user_id)
 */
class TenantController extends Controller
{
    private function validDate(string $s): bool
    {
        $d = DateTime::createFromFormat('Y-m-d', $s);
        return $d !== false && $d->format('Y-m-d') === $s;
    }

    public function index(Request $request)
    {
        if ($request->query('rooms')) {
            return ApiResponse::ok(TenantService::assignableRooms());
        }
        return ApiResponse::ok(TenantService::search([
            'q'        => trim((string) $request->query('q', '')),
            'page'     => (int) $request->query('page', 1),
            'per_page' => (int) $request->query('per_page', 10),
        ]));
    }

    public function store(Request $request)
    {
        $action = (string) $request->input('action', '');

        if ($action === 'create') {
            return $this->create($request);
        }

        if ($action === 'active') {
            $v = new Validator($request->all());
            $v->positiveInt('user_id', 'Tenant')->inList('active', ['0', '1'], 'Status');
            if ($v->fails()) {
                return ApiResponse::error('Invalid request.', 422, $v->errors());
            }
            if (!TenantService::setActive((int) $v->get('user_id'), $v->get('active') === '1')) {
                return ApiResponse::error('Tenant not found or nothing changed.', 404);
            }
            return ApiResponse::ok(null, $v->get('active') === '1' ? 'Tenant account enabled.' : 'Tenant account disabled.');
        }

        if ($action === 'reset_password') {
            $v = new Validator($request->all());
            $v->positiveInt('user_id', 'Tenant');
            if ($v->fails()) {
                return ApiResponse::error('Invalid request.', 422, $v->errors());
            }
            $pw = TenantService::resetPassword((int) $v->get('user_id'));
            return ApiResponse::ok(['temporary_password' => $pw], 'Password reset.');
        }

        return ApiResponse::error('Unknown action.', 400);
    }

    private function create(Request $request)
    {
        $v = new Validator($request->all());
        $v->required('full_name', 'Full name')->maxLength('full_name', 120, 'Full name')
          ->required('email', 'Email')->email('email', 'Email')->maxLength('email', 150, 'Email')
          ->maxLength('phone', 30, 'Phone')
          ->maxLength('address', 255, 'Address')->maxLength('occupation', 100, 'Occupation')
          ->maxLength('id_type', 50, 'ID type')
          ->maxLength('emergency_name', 120, 'Emergency contact')
          ->maxLength('emergency_phone', 30, 'Emergency phone')
          ->maxLength('emergency_relation', 50, 'Relationship');

        $password  = (string) $request->input('password', '');      // never trimmed
        $generated = false;
        if ($password === '') {
            $password  = TenantService::generatePassword();
            $generated = true;
        } elseif (mb_strlen($password) < 8) {
            return ApiResponse::error('Please fix the highlighted fields.', 422, ['password' => 'Password must be at least 8 characters (or leave blank to auto-generate).']);
        }

        $errors = $v->errors();
        $birth  = $v->get('birth_date');
        if ($birth !== '' && (!$this->validDate($birth) || $birth >= date('Y-m-d'))) {
            $errors['birth_date'] = 'Enter a valid birth date in the past.';
        }

        $roomId = (int) $v->get('room_id', '0');
        $lease  = ['start_date' => '', 'end_date' => '', 'security_deposit' => '0', 'billing_day' => '5'];
        if ($roomId > 0) {
            foreach (['start_date', 'end_date'] as $k) {
                $lease[$k] = $v->get($k);
                if (!$this->validDate($lease[$k])) {
                    $errors[$k] = 'Enter a valid date.';
                }
            }
            if (!isset($errors['start_date'], $errors['end_date']) && $lease['end_date'] <= $lease['start_date']) {
                $errors['end_date'] = 'End date must be after the start date.';
            }
            $lease['security_deposit'] = $v->get('security_deposit', '0') ?: '0';
            if (!is_numeric($lease['security_deposit']) || (float) $lease['security_deposit'] < 0) {
                $errors['security_deposit'] = 'Deposit must be zero or more.';
            }
            $lease['billing_day'] = $v->get('billing_day', '5') ?: '5';
            $bd = filter_var($lease['billing_day'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 28]]);
            if ($bd === false) {
                $errors['billing_day'] = 'Billing day must be 1 to 28.';
            }
        }

        if (!$errors && Db::fetchValue('SELECT id FROM users WHERE email = ?', [mb_strtolower($v->get('email'))])) {
            $errors['email'] = 'That email is already registered.';
        }
        if ($errors) {
            return ApiResponse::error('Please fix the highlighted fields.', 422, $errors);
        }

        $ids = TenantService::create([
            'full_name'  => $v->get('full_name'),
            'email'      => mb_strtolower($v->get('email')),
            'password'   => $password,
            'phone'      => $v->get('phone'),
            'birth_date' => $birth,
            'address'    => $v->get('address'),
            'occupation' => $v->get('occupation'),
            'id_type'    => $v->get('id_type'),
            'emergency_name'     => $v->get('emergency_name'),
            'emergency_phone'    => $v->get('emergency_phone'),
            'emergency_relation' => $v->get('emergency_relation'),
            'room_id'    => $roomId,
            'start_date' => $lease['start_date'],
            'end_date'   => $lease['end_date'],
            'security_deposit' => $lease['security_deposit'],
            'billing_day'      => (int) $lease['billing_day'],
            'deposit_received' => !empty($request->input('deposit_received')),
        ], (int) $request->user()->id);

        NotificationService::send($ids['user_id'], 'welcome', 'Welcome! Your tenant account is ready.', 'dashboard');

        // Plain password is returned ONCE so the manager can hand it to the tenant.
        return ApiResponse::ok([
            'user_id'            => $ids['user_id'],
            'tenant_id'          => $ids['tenant_id'],
            'lease_id'           => $ids['lease_id'],
            'email'              => mb_strtolower($v->get('email')),
            'temporary_password' => $password,
            'generated'          => $generated,
        ], 'Tenant account created.');
    }
}
