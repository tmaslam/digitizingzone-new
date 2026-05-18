<?php

namespace App\Http\Middleware;

use App\Support\SiteContext;
use App\Support\SiteResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class DetectSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $site = SiteResolver::forRequest($request);

        $request->attributes->set('siteContext', $site);
        app()->instance(SiteContext::class, $site);
        View::share('siteContext', $site);

        return $next($request);
    }
}
