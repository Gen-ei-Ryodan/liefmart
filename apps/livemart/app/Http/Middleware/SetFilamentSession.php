<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetFilamentSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session()->has('main_category_id')) {
            session(['main_category_id' => \App\Helpers\MainCategoryHelper::getCosmeticCategoryId()]);
            session(['main_category_name' => 'Kosmetik']);
        }

        return $next($request);
    }
}
