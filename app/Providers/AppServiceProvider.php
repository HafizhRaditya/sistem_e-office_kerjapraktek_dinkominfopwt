<?php

namespace App\Providers;

use App\Services\KeycloakOidcService;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\ServiceProvider;
use Psr\Http\Client\ClientInterface as PsrHttpClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // PSR-18 transport for the Keycloak OIDC flow. Bound explicitly instead
        // of relying on Psr18ClientDiscovery: Guzzle and symfony/http-client are
        // both installed, so discovery could pick either. Tests rebind this key
        // with a mocked handler so no test reaches a real Keycloak.
        $this->app->bind(PsrHttpClient::class, static fn (): GuzzleClient => new GuzzleClient([
            'timeout' => 10,
            'connect_timeout' => 5,
        ]));

        $this->app->singleton(
            KeycloakOidcService::class,
            static fn ($app): KeycloakOidcService => new KeycloakOidcService($app->make(PsrHttpClient::class)),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
