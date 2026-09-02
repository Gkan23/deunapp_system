<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class RegisterCustomerPageController extends Controller
{
    /**
     * Display the customer registration page.
     */
    public function __invoke(): View
    {
        return view('auth.register-customer');
    }
}