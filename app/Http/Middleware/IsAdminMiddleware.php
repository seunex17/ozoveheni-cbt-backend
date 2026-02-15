<?php

/**
 * Copyright (C) ZubDev Digital Media - All Rights Reserved
 *
 * File: IsAdminMiddleware.php
 * Author: Zubayr Ganiyu
 *   Email: <seunexseun@gmail.com>
 *   Website: https://zubdev.net
 * Date: 2/15/26
 * Time: 3:31 PM
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user->is_admin) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
