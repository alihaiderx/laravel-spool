<?php

namespace Alihaiderx\LaravelSpool\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureDevEnv {

  public function handle(Request $request, Closure $next){
    
    return $next($request);

  }

}


?>