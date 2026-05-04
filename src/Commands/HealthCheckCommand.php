<?php

namespace Alihaiderx\LaravelSpool\Commands;

use Alihaiderx\LaravelSpool\Support\PackageHealthChecker;
use Illuminate\Console\Command;

class HealthCheckCommand extends Command
{
    protected $signature = 'spool:health';
    protected $description = 'Check the health of the Laravel Spool package.';

    public function handle(): int
    {
        $result = (new PackageHealthChecker())->check();

        if ($result['healthy']) {
            $this->info('All checks passed.');
            return self::SUCCESS;
        }

        foreach ($result['errors'] as $error) {
            $this->error($error);
        }

        return self::FAILURE;
    }
}
