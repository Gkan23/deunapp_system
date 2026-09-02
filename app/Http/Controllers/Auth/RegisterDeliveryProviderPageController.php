<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class RegisterDeliveryProviderPageController extends Controller
{
    /**
     * Display the provider registration page.
     */
    public function __invoke(): View
    {
        return view(
            'auth.register-provider'
        );
    }
}