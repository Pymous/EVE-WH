<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class apiFull extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:full';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all the needed commands to import the full SDE and update the prices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->call('sde:import');
        $this->call('api:esi:prices');
    }
}
