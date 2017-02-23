<?php

namespace Nova\Foundation\Console;

use Nova\Console\Command;


class UpCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Bring the Application out of Maintenance Mode";

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $config = $this->nova['config'];

        @unlink($config['path.storage'] .DS .'down');

        $this->info('Application is now live.');
    }

}
