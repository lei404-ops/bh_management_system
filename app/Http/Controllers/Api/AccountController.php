<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuthService;
use App\Support\ApiResponse;
use DomainException;
use Illuminate\Http\Request;

/**
 * Account API (any logged-in role)
 *   POST /api/account  action=change_password  current_password, new_password, confirm_password
 */
class AccountController extends Controller
{
    public function store(Request $request)
    {
        if ($request->input('action') !== 'change_password') {
            return ApiResponse::error('Unknown action.', 400);
        }

        // Passwords are never trimmed: spaces may be intentional.
        $current = (string) $request->input('current_password', '');
        $new     = (string) $request->input('new_password', '');
        $confirm = (string) $request->input('confirm_password', '');

        $errors = [];
        if ($current === '') {
            $errors['current_password'] = 'Enter your current password.';
        }
        if (mb_strlen($new) < 8) {
            $errors['new_password'] = 'New password must be at least 8 characters.';
        } elseif (mb_strlen($new) > 72) {
            $errors['new_password'] = 'New password must be at most 72 characters.';   // bcrypt limit
        } elseif (!preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors['new_password'] = 'Use at least one letter and one number.';
        }
        if (!isset($errors['new_password']) && !hash_equals($new, $confirm)) {
            $errors['confirm_password'] = 'The two new passwords do not match.';
        }
        if ($errors) {
            return ApiResponse::error('Please fix the highlighted fields.', 422, $errors);
        }

        try {
            AuthService::changePassword((int) $request->user()->id, $current, $new, (string) $request->ip());
        } catch (DomainException $e) {
            $field = str_contains($e->getMessage(), 'different') ? 'new_password' : 'current_password';
            return ApiResponse::error($e->getMessage(), 422, [$field => $e->getMessage()]);
        }

        $request->session()->regenerate();   // new session id after a credential change
        return ApiResponse::ok(null, 'Password changed successfully.');
    }
}
