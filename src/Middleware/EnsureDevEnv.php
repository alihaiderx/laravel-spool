<?php

namespace Alihaiderx\LaravelSpool\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureDevEnv
{

  public function handle(Request $request, Closure $next)
  {

    $envs = ['local', 'dev', 'development'];

    if (!in_array(strtolower(config('app.env')), $envs)) {
      abort(401);
    }

    return $next($request);
  }
}
