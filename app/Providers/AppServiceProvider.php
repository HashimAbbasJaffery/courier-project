<?php

namespace App\Providers;

use App\Classes\CourierServices\LeopardCourier;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        app()->bind("leopard", function() {
            $env = env('LEOPARD_ENV', 'production');
            $endpoint = $env === 'staging'
                ? 'https://merchantapistaging.leopardscourier.com/api'
                : 'https://merchantapi.leopardscourier.com/api';
            
            \Log::info('Creating LeopardCourier service', [
                'env' => $env,
                'endpoint' => $endpoint,
                'api_key_set' => !empty(env('LEOPARD_API_KEY')),
                'password_set' => !empty(env('LEOPARD_API_PASSWORD'))
            ]);
                
            return new LeopardCourier(env("LEOPARD_API_KEY"), env("LEOPARD_API_PASSWORD"), $endpoint);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
