<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: AccountActiveMiddleware.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/5/26
 * Time: 12:36 PM
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AccountActiveMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()->status !== 'active') {
            \Auth::logout();

            return redirect()
                ->route('home')
                ->with('error', 'Your account is not active.');

        }

        return $next($request);
    }
}
