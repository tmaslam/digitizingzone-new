<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthLandingController extends Controller
{
    public function show(Request $request)
    {
        if ($request->session()->has('admin_user_id')) {
            return redirect(url('/welcome.php'));
        }

        if ($request->session()->has('team_user_id')) {
            return redirect(url('/team/welcome.php'));
        }

        return view('auth.landing');
    }
}
