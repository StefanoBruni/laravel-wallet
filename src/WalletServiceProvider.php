<?php

namespace StefanoBruni\Wallet;

use Illuminate\Support\ServiceProvider;

class WalletServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
	public function register()
    {
	    $config = __DIR__ . '/../config/cart.php';

	    $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'wallet');

            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('wallet.php'),
            ], 'config');

            $this->publishes([
                __DIR__ . '/../resources/migrations/' => database_path('migrations'),
            ], 'migrations');


    }
}
