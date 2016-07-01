<?php

namespace Nova\Modules\Providers;

use Nova\Support\ServiceProvider;


class GeneratorServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        //
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->registerMakeModuleCommand();
        //$this->registerMakeControllerCommand();
        //$this->registerMakeModelCommand();
        //$this->registerMakeMigrationCommand();
        $this->registerMakeSeederCommand();
    }


    /**
     * Register the make:module command.
     */
    private function registerMakeModuleCommand()
    {
        $this->app->bindShared('command.make.module', function ($app) {
            return $app['Nova\Modules\Console\Generators\MakeModuleCommand'];
        });

        $this->commands('command.make.module');
    }

    /**
     * Register the make:module:controller command.
     */
    private function registerMakeControllerCommand()
    {
        $this->app->bindShared('command.make.module.controller', function ($app) {
            return $app['Nova\Modules\Console\Generators\MakeControllerCommand'];
        });

        $this->commands('command.make.module.controller');
    }

    /**
     * Register the make:module:model command.
     */
    private function registerMakeModelCommand()
    {
        $this->app->bindShared('command.make.module.model', function ($app) {
            return $app['Nova\Modules\Console\Generators\MakeModelCommand'];
        });

        $this->commands('command.make.module.model');
    }

    /**
     * Register the make:module:migration command.
     */
    private function registerMakeMigrationCommand()
    {
        $this->app->bindShared('command.make.module.migration', function ($app) {
            return $app['Nova\Modules\Console\Generators\MakeMigrationCommand'];
        });

        $this->commands('command.make.module.migration');
    }

    /**
     * Register the make:module:seeder command.
     */
    private function registerMakeSeederCommand()
    {
        $this->app->bindShared('command.make.module.seeder', function ($app) {
            return $app['Nova\Modules\Console\Generators\MakeSeederCommand'];
        });

        $this->commands('command.make.module.seeder');
    }
}