<?php namespace Reckless\Table\Providers;

use Reckless\Table\Table;
use Illuminate\Support\ServiceProvider;

class TableServiceProvider extends ServiceProvider {

    /**
     * Register bindings in the container.
     * @return void
     */
    public function register()
    {
        $this->app->bind('table', function()
        {
            return new Table;
        });
    }

    /**
     * Bootstrap the application files.
     * @return void
     */
    public function boot()
    {
        $root = __DIR__.'/../../';
        // Load views
        $this->loadViewsFrom($root . 'resources/views', 'reckless');

        // Publish views
        $this->publishes([
            $root . 'resources/views' => base_path('resources/views/vendor/reckless'),
        ]);

        // Publish configuration
        $this->publishes([
            $root . 'config/tables.php' => config_path('reckless-tables.php'),
        ]);

        // Merge user config, passing in our defaults
        $this->mergeConfigFrom(
            $root . 'config/tables.php', 'reckless-tables'
        );

        // Publish assets
//        $this->publishes([
//            $root . 'build/assets' => public_path('vendor/reckless/tables'),
//        ], 'public');
    }
}
