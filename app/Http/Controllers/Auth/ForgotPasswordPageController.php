<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ForgotPasswordPageController extends Controller
{
    /**
     * Display the password recovery page.
     */
    public function __invoke(): View
    {
        return view('auth.forgot-password');
    }
}