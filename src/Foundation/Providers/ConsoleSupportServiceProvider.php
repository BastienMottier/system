<?php

namespace Nova\Foundation\Providers;

use Nova\Foundation\Composer;
use Nova\Foundation\Forge;
use Nova\Support\ServiceProvider;


class ConsoleSupportServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the Provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the Service Provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bindShared('composer', function($app)
        {
            return new Composer($app['files'], $app['path.base']);
        });

        $this->app->bindShared('forge', function($app)
        {
           return new Forge($app);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('composer', 'forge');
    }

}
