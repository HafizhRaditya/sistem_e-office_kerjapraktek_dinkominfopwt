<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Services\KeycloakOidcService;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
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
        /**
         * Carry the admin gate onto Livewire's update endpoint.
         *
         * The admin tables (Hak Akses, Pengguna, Aplikasi) are Livewire
         * components. Their PAGE is protected by ['auth', EnsureUserIsAdmin],
         * but a search keystroke or a page change posts to Livewire's own
         * endpoint, which is registered with ['web', RequireLivewireHeaders].
         *
         * Livewire replays the ORIGINAL route's middleware there, but only the
         * classes on its allow-list — and that list ships with Illuminate's
         * Authenticate on it, not our EnsureUserIsAdmin. So 'auth' was enforced
         * and the admin gate was not: anyone holding a snapshot that had been
         * rendered for an admin could keep reading those tables as a pegawai.
         * LivewireAdminGuardTest reproduces exactly that.
         *
         * Registering it here closes all three components at once. The
         * components also assert the role themselves (see their boot() hooks),
         * because this line silently protects nothing if a future upgrade
         * changes how Livewire gathers middleware.
         */
        Livewire::addPersistentMiddleware([
            EnsureUserIsAdmin::class,
        ]);
    }
}
