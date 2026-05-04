<?php

namespace Alihaiderx\LaravelSpool\Support;

use Alihaiderx\LaravelSpool\Services\FileSystemBufferService;

class PackageHealthChecker
{
    private array $errors = [];

    public function check(): array
    {
        $this->errors = [];

        $this->checkConfig();
        $this->checkDirectories();

        return [
            'healthy' => empty($this->errors),
            'errors'  => $this->errors,
        ];
    }

    private function checkConfig(): void
    {
        $config = config('spool');

        if (!in_array($config['buffer_driver'], ['file', 'redis'])) {
            $this->errors[] = "Invalid buffer_driver '{$config['buffer_driver']}'. Must be 'file' or 'redis'.";
        }

        if (!is_int($config['max_shards']) || $config['max_shards'] < 1) {
            $this->errors[] = "max_shards must be an integer >= 1.";
        }

        if (!is_int($config['max_shard_size']) || $config['max_shard_size'] < 1) {
            $this->errors[] = "max_shard_size must be an integer >= 1.";
        }

        if (!is_int($config['max_flush_shards']) || $config['max_flush_shards'] < 1) {
            $this->errors[] = "max_flush_shards must be an integer >= 1.";
        }

        if ($config['max_flush_shards'] > $config['max_shards']) {
            $this->errors[] = "max_flush_shards ({$config['max_flush_shards']}) cannot exceed max_shards ({$config['max_shards']}).";
        }

        if (!is_int($config['shards_ttl_days']) || $config['shards_ttl_days'] < 1) {
            $this->errors[] = "shards_ttl_days must be an integer >= 1.";
        }

        if (!is_int($config['redis_batch_size']) || $config['redis_batch_size'] < 1) {
            $this->errors[] = "redis_batch_size must be an integer >= 1.";
        }
    }

    private function checkDirectories(): void
    {
        $dirs = (new FileSystemBufferService())->getDirs();

        $checks = [
            'active'     => $dirs['bufferActive'],
            'processing' => $dirs['bufferProcessing'],
            'completed'  => $dirs['bufferCompleted'],
        ];

        foreach ($checks as $name => $relative) {
            $path = storage_path($relative);

            if (!file_exists($path)) {
                $this->errors[] = "Buffer '{$name}' directory does not exist: {$path}";
                continue;
            }

            if (!is_writable($path)) {
                $this->errors[] = "Buffer '{$name}' directory is not writable: {$path}";
            }
        }
    }
}
