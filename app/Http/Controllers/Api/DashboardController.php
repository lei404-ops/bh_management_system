<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

/** GET /api/dashboard -> metrics shaped for the caller's role (used for live refresh). */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user()->asArray();

        $data = match ($user['role']) {
            'admin', 'landlord' => DashboardService::overview(),
            'tenant'            => DashboardService::forTenant($user['id']),
            'staff'             => DashboardService::forStaff($user['id']),
        };
        $data['notifications'] = NotificationService::forUser($user['id']);

        return ApiResponse::ok($data);
    }
}
