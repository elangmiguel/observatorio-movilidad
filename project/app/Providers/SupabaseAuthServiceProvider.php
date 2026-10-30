<?php

namespace App\Providers;

use App\Services\SupabaseJwt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class SupabaseAuthServiceProvider extends ServiceProvider
{
    public function boot() : void
    {
        // Guard 'supabase': Resuelve el Usuario a partir del token de Supabase Auth.
        Auth::viaRequest('supabase', fn (Request $request) => $this->app->make(SupabaseJwt::class)->usuario($request));
    }
}
