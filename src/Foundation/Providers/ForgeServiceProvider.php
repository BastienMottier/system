<?php

namespace Nova\Foundation\Providers;

use Nova\Foundation\Console\UpCommand;
use Nova\Foundation\Console\DownCommand;
use Nova\Foundation\Console\ServeCommand;
use Nova\Foundation\Console\OptimizeCommand;
use Nova\Foundation\Console\RouteListCommand;
use Nova\Foundation\Console\ModelMakeCommand;
use Nova\Foundation\Console\ViewClearCommand;
use Nova\Foundation\Console\ConsoleMakeCommand;
use Nova\Foundation\Console\EnvironmentCommand;
use Nova\Foundation\Console\KeyGenerateCommand;
use Nova\Foundation\Console\PolicyMakeCommand;
use Nova\Foundation\Console\ProviderMakeCommand;
use Nova\Support\ServiceProvider;


class ForgeServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * The commands to be registered.
     *
     * @var array
     */
    protected $commands = array(
        'ConsoleMake'    => 'command.console.make',
        'Down'           => 'command.down',
        'Environment'    => 'command.environment',
        'KeyGenerate'    => 'command.key.generate',
        'ModelMake'      => 'command.model.make',
        'Optimize'       => 'command.optimize',
        'PolicyMake'     => 'command.policy.make',
        'ProviderMake'   => 'command.provider.make',
        'RouteList'      => 'command.route.list',
        'Serve'          => 'command.serve',
        'Up'             => 'command.up',
        'ViewClear'      => 'command.view.clear',
    );

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        foreach (array_keys($this->commands) as $command) {
            $method = "register{$command}Command";

            call_user_func_array(array($this, $method), array());
        }

        $this->commands(array_values($this->commands));
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerConsoleMakeCommand()
    {
        $this->app->singleton('command.console.make', function ($app)
        {
            return new ConsoleMakeCommand($app['files']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerDownCommand()
    {
        $this->app->singleton('command.down', function ()
        {
            return new DownCommand;
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerEnvironmentCommand()
    {
        $this->app->singleton('command.environment', function ()
        {
            return new EnvironmentCommand;
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerKeyGenerateCommand()
    {
        $this->app->singleton('command.key.generate', function ($app)
        {
            return new KeyGenerateCommand($app['files']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerModelMakeCommand()
    {
        $this->app->singleton('command.model.make', function ($app)
        {
            return new ModelMakeCommand($app['files']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerOptimizeCommand()
    {
        $this->app->singleton('command.optimize', function ($app)
        {
            return new OptimizeCommand($app['composer']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerPolicyMakeCommand()
    {
        $this->app->singleton('command.policy.make', function ($app)
        {
            return new PolicyMakeCommand($app['files']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerProviderMakeCommand()
    {
        $this->app->singleton('command.provider.make', function ($app)
        {
            return new ProviderMakeCommand($app['files']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerRouteListCommand()
    {
        $this->app->singleton('command.route.list', function ($app)
        {
            return new RouteListCommand($app['router']);
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerServeCommand()
    {
        $this->app->singleton('command.serve', function ()
        {
            return new ServeCommand;
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerUpCommand()
    {
        $this->app->singleton('command.up', function ()
        {
            return new UpCommand;
        });
    }

    /**
     * Register the command.
     *
     * @return void
     */
    protected function registerViewClearCommand()
    {
        $this->app->singleton('command.view.clear', function ($app)
        {
            return new ViewClearCommand($app['files']);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array_values($this->commands);
    }
}
