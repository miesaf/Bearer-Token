<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * Passport registers its routes and grants while it boots, which happens
     * before this provider boots, so its switches are set here.
     */
    public function register(): void
    {
        // Keep the integer client ids the existing oauth_clients rows use.
        Passport::$clientUuids = false;

        // Keep the grants and JSON API that Passport 9 offered: the password grant,
        // and /oauth/clients, /oauth/scopes, /oauth/personal-access-tokens and
        // /oauth/tokens used by the passport-* Vue components.
        Passport::enablePasswordGrant();
        Passport::$registersJsonApiRoutes = true;

        // Passport 9 had no device code grant.
        Passport::$deviceCodeGrantEnabled = false;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Passport 13 is headless: the authorization prompt is the app's own view.
        Passport::authorizationView('passport.authorize');

        // The views use Bootstrap markup.
        Paginator::useBootstrapFive();
    }
}
