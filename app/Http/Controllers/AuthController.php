<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Support\Validator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Login / logout (classic form POST, protected by Laravel's CSRF middleware). */
class AuthController extends Controller
{
    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login', ['error' => session('error')]);
    }

    public function login(Request $request)
    {
        $v = new Validator($request->all());
        $v->required('email', 'Email')->email('email', 'Email')->required('password', 'Password');
        if ($v->fails()) {
            return $this->back($request, implode(' ', $v->errors()));
        }

        // Passwords are NOT trimmed: spaces may be intentional.
        [$user, $message] = AuthService::attempt($v->get('email'), (string) $request->input('password', ''), (string) $request->ip());
        if ($user === null) {
            return $this->back($request, $message);
        }

        Auth::login($user);                   // also regenerates the session id (prevents session fixation)
        $request->session()->regenerateToken();
        return redirect()->route('dashboard');   // every role lands on /dashboard (role-specific view)
    }

    /** Logout must be a POST with a CSRF token so other sites cannot log users out. */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    private function back(Request $request, string $error)
    {
        return response()->view('auth.login', ['error' => $error, 'email' => (string) $request->input('email', '')]);
    }
}
