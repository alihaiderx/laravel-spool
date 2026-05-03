<?php

namespace Alihaiderx\LaravelSpool\Commands;

use Alihaiderx\LaravelSpool\Facades\FileSystemBuffer;
use Exception;
use Illuminate\Console\Command;

class InstallCommand extends Command
{

  protected $signature = 'spool:install';
  protected $description = 'Install the Laravel Spool package.';

  public function handle(): void
  {

    try {

      $this->info('Installing the Laravel Spool package...');

      $this->info('Publishing the resources...');
      $this->call('vendor:publish', [
        '--tag' => 'spool',
        '--force' => false
      ]);

      $this->info('Setup project dir(s)...');
      FileSystemBuffer::setupDirs();

      $this->info('Laravel Spool package installed.');

    } catch (Exception $e) {
      $this->error($e->getMessage());
    }
  }
}
