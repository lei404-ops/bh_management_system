<?php

namespace App\Http\Controllers;

use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Pages (HTML shells). The data is loaded by app.js from the /api endpoints. */
class PageController extends Controller
{
    public function home()
    {
        return redirect()->route(Auth::check() ? 'dashboard' : 'login');
    }

    public function dashboard(Request $request)
    {
        $role = $request->user()->role;
        $view = match ($role) {
            'admin', 'landlord' => 'admin',
            'tenant'            => 'tenant',
            'staff'             => 'staff',
        };

        return view("dashboard.$view", [
            'pageTitle'  => 'Dashboard',
            'activePage' => 'dashboard',
            'pageKey'    => 'dashboard-' . $view,   // app.js loads the matching widgets
        ]);
    }

    public function rooms(Request $request)
    {
        $role = $request->user()->role;
        return view('pages.rooms', [
            'pageTitle'  => $role === 'tenant' ? 'Browse Rooms' : 'Rooms',
            'activePage' => 'rooms',
            'canManage'  => in_array($role, ['admin', 'landlord'], true),
        ]);
    }

    public function tenants()
    {
        return view('pages.tenants', ['pageTitle' => 'Tenants', 'activePage' => 'tenants']);
    }

    public function maintenance(Request $request)
    {
        $role = $request->user()->role;
        return view('pages.maintenance', [
            'pageTitle'  => match ($role) { 'tenant' => 'My Maintenance Requests', 'staff' => 'My Tickets', default => 'Maintenance Tickets' },
            'activePage' => 'maintenance',
        ]);
    }

    public function account()
    {
        return view('pages.account', ['pageTitle' => 'Change Password', 'activePage' => 'account']);
    }

    /** Printable payment receipt (admin, landlord, or the tenant who owns it). */
    public function receipt(Request $request)
    {
        $p = PaymentService::receipt((int) $request->query('id', 0), $request->user()->asArray());
        abort_if(!$p, 404, 'Receipt not found.');

        $methods = ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'gcash' => 'GCash', 'maya' => 'Maya', 'mock_gateway' => 'Demo gateway'];
        return view('pages.receipt', ['p' => $p, 'methodLabel' => $methods[$p['method']] ?? $p['method']]);
    }
}
