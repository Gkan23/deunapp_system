<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class LoginPageController extends Controller
{
    /**
     * Display the login page.
     */
    public function __invoke(): View
    {
        return view('auth.login');
    }
}