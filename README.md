<p align="center">
  <img src="art/logo.png" alt="Laravel Spool" width="350">
</p>

<p align="center">
  <a href="https://opensource.org/licenses/MIT">
    <img src="https://img.shields.io/badge/License-MIT-yellow.svg" alt="License: MIT">
  </a>
  <a href="https://php.net">
    <img src="https://img.shields.io/badge/PHP-8.2+-blue.svg" alt="PHP 8.2+">
  </a>
  <a href="https://laravel.com">
    <img src="https://img.shields.io/badge/Laravel-12.x-red.svg" alt="Laravel 12.x">
  </a>
</p>

<p align="center">
  A fast, non-blocking write buffer for Laravel. Accumulate high-frequency data into sharded files or Redis Streams and process them in batches on your own schedule.
</p>

## What Is Laravel Spool?

Laravel Spool is a Laravel package that gives you a simple, reliable buffer layer for high-frequency writes. Instead of hitting your database or an external service on every single event, Spool captures the data into a fast buffer first. You then process those entries in a batch whenever you are ready, keeping your application responsive and your storage layer under control.

Spool supports two buffer drivers: a **filesystem driver** that writes to sharded local files, and a **Redis Streams driver** for lower-latency buffering with a long-running consumer process. Both share the same `Buffer` facade so switching between them requires minimal code changes.

## The Problem It Solves

High-frequency events are common in web applications: page views, API calls, activity logs, metrics, and so on. Writing each one directly to a database is expensive. Under load, it creates lock contention, slows down response times, and can bring down a production server.

The usual workarounds (queues, Redis lists, log pipelines) introduce operational overhead and extra infrastructure. Spool is designed to be the simplest possible answer: write fast to a buffer, process in a batch on a schedule.

## Requirements

- PHP 8.2+
- Laravel 12.x
- Redis driver requires the `phpredis` extension or `predis/predis`

## Installation

### Via Composer

```bash
composer require alihaiderx/laravel-spool
```

Laravel will auto-discover the service provider. No manual registration is needed.

### Manual (without auto-discovery)

If you have auto-discovery disabled, register the provider in `bootstrap/providers.php`:

```php
Alihaiderx\LaravelSpool\Providers\AppServiceProvider::class,
```

## Getting Started

After installing the package, run the install command:

```bash
php artisan spool:install
```

This does two things:

1. Publishes the `spool.php` config file to your `config/` directory.
2. Creates the required buffer directories under `storage/app/private/buffer/`.

## Buffer Drivers

### The `Buffer` Facade (Recommended)

The `Buffer` facade is the recommended way to write to the buffer. It automatically uses the Redis driver when Redis is available and `SPOOL_BUFFER_DRIVER=redis` is set, and falls back to the filesystem driver otherwise. This means your application code stays the same regardless of which driver is active.

```php
use Alihaiderx\LaravelSpool\Facades\Buffer;

Buffer::buffer([
    'payload' => [
        'user_id' => 42,
        'event'   => 'page_view',
        'url'     => '/home',
    ]
], 'page-views');
```

The second argument is the **bucket slug**. It namespaces your data so you can have multiple independent buffers in the same application (e.g. `'page-views'`, `'api-logs'`, `'metrics'`).

### Filesystem Driver

The filesystem driver requires no external dependencies. It writes payloads into small, sharded log files on disk and moves them through a three-stage lifecycle.

#### How it works

When you call `buffer()`, Spool serializes the payload and appends it to a shard file. The shard is chosen using a hash of the payload modulo `max_shards`, so writes are spread across multiple files to reduce contention. Once a shard reaches `max_shard_size`, it is rotated and a fresh file takes its place.

When you call `flush()`, Spool atomically moves shards from `active/` to `processing/` using a file rename, runs your callback against each file, and then moves them to `completed/`. This rename ensures two workers can never process the same shard simultaneously. Completed shards are cleaned up by `clean()` after their TTL expires.

#### Directory structure

```
storage/app/private/buffer/
├── active/
├── processing/
└── completed/
```

| Directory | Purpose |
|-----------|---------|
| `active/` | Shards currently being written to. New payloads are appended here. |
| `processing/` | Shards that have been picked up by a flush job. Moving a file here is an atomic rename, so no two workers will ever process the same shard. |
| `completed/` | Shards that have been successfully processed. Kept for `shards_ttl_days` days as a short-term audit trail, then deleted by `clean()`. |

#### Activating the filesystem driver

This is the default. No changes are needed in your `.env`. If you are switching back from Redis:

```dotenv
SPOOL_BUFFER_DRIVER=file
```

#### Usage

**Buffer a payload:**

```php
use Alihaiderx\LaravelSpool\Facades\FileSystemBuffer;

FileSystemBuffer::buffer([
    'payload' => [
        'user_id' => 42,
        'event'   => 'page_view',
    ]
], 'page-views');
```

**Flush a bucket (typically in a scheduled command):**

```php
FileSystemBuffer::flush(function (string $filePath, ?string $bucketSlug): bool {
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $rows  = array_map(fn ($line) => unserialize($line), $lines);

    // Insert $rows into your database, send to an API, etc.

    return true; // Returning true moves the shard to completed/.
}, 'page-views');
```

Returning `true` from the callback moves the shard to `completed/`. Returning anything falsy leaves it in `processing/` so you can inspect or retry it manually.

**Clean up old completed shards:**

```php
FileSystemBuffer::clean(50, 'page-views');
```

The first argument limits how many files are checked in a single call. Run this on a daily schedule to keep disk usage in check.

### Redis Driver

The Redis driver writes payloads to a **Redis Stream** using `XADD`. A separate long-running process reads from the stream in batches and fires a Laravel event that you listen to in your application code.

This driver is a good fit when you need lower write latency than the filesystem provides, or when your infrastructure already runs Redis.

#### Prerequisites

Install `predis/predis` if you are not using the `phpredis` PHP extension:

```bash
composer require predis/predis
```

#### Activating the Redis driver

Set the following in your `.env`:

```dotenv
SPOOL_BUFFER_DRIVER=redis
```

Make sure your `REDIS_HOST`, `REDIS_PORT`, and `REDIS_PASSWORD` are configured as you would for any other Laravel Redis connection.

#### Starting the consumer

The Redis driver requires a long-running process that reads messages off the stream and fires them as batched events. Start it with:

```bash
php artisan spool:start-redis-consume
```

This process blocks and runs continuously. In production, manage it with **Supervisor** (or a similar process monitor) so it restarts automatically if it stops.

A minimal Supervisor config:

```ini
[program:spool-redis-consumer]
command=php /var/www/html/artisan spool:start-redis-consume
autostart=true
autorestart=true
stderr_logfile=/var/log/spool-consumer.err.log
stdout_logfile=/var/log/spool-consumer.out.log
```

#### Listening for consumed batches

When the consumer reads a batch from Redis, it fires `RedisBufferConsumeEvent`. Register a listener for it wherever you set up your application logic, for example in a service provider or `AppServiceProvider::boot()`:

```php
use Alihaiderx\LaravelSpool\Facades\Buffer;
use Alihaiderx\LaravelSpool\Events\RedisBufferConsumeEvent;

Buffer::listenRedis(function (RedisBufferConsumeEvent $event) {
    $batch = $event->batch;
    // Each item in $batch has 'payload' and 'bucketSlug' keys.

    $pageViews = array_filter($batch, fn ($item) => $item['bucketSlug'] === 'page-views');

    // Insert into your database, push to an analytics service, etc.
});
```

The `$event->batch` array contains all messages consumed in a single read, up to `redis_batch_size` items. Each entry has:

| Key | Description |
|-----|-------------|
| `payload` | The unserialized payload you passed to `buffer()`. |
| `bucketSlug` | The bucket name you passed when buffering. |

#### Buffering with the Redis driver

The API is identical to the filesystem driver:

```php
use Alihaiderx\LaravelSpool\Facades\Buffer;

Buffer::buffer([
    'payload' => ['user_id' => 42, 'event' => 'page_view']
], 'page-views');
```

You can also use the `RedisBuffer` facade directly if you want to target Redis explicitly:

```php
use Alihaiderx\LaravelSpool\Facades\RedisBuffer;

RedisBuffer::buffer([
    'payload' => ['user_id' => 42, 'event' => 'page_view']
], 'page-views');
```

## Configuration

After running `spool:install`, the config lives at `config/spool.php`. All values can be set via `.env`.

```php
return [
    'buffer_driver'    => env('SPOOL_BUFFER_DRIVER', 'file'),
    'max_shards'       => env('SPOOL_MAX_SHARDS', 30),
    'max_shard_size'   => env('SPOOL_MAX_SHARD_SIZE', 307200),
    'max_flush_shards' => env('SPOOL_MAX_FLUSH_SHARDS', 5),
    'shards_ttl_days'  => env('SPOOL_SHARDS_TTL_DAYS', 3),
    'redis_batch_size' => env('SPOOL_REDIS_BATCH_SIZE', 500),
];
```

| Key | Env variable | Default | Driver | Description |
|-----|-------------|---------|--------|-------------|
| `buffer_driver` | `SPOOL_BUFFER_DRIVER` | `file` | Both | Which driver to use. Accepted values: `file`, `redis`. |
| `max_shards` | `SPOOL_MAX_SHARDS` | `30` | Filesystem | Number of shard files per bucket. Higher values spread writes across more files, reducing lock contention under heavy load. |
| `max_shard_size` | `SPOOL_MAX_SHARD_SIZE` | `307200` (300 KB) | Filesystem | Maximum size of a shard file in bytes. Once a shard exceeds this, it is rotated and a fresh file is used. |
| `max_flush_shards` | `SPOOL_MAX_FLUSH_SHARDS` | `5` | Filesystem | How many shards to process in a single `flush()` call. Keeps flush jobs short and predictable. |
| `shards_ttl_days` | `SPOOL_SHARDS_TTL_DAYS` | `3` | Filesystem | How many days to keep completed shards before `clean()` deletes them. |
| `redis_batch_size` | `SPOOL_REDIS_BATCH_SIZE` | `500` | Redis | How many messages the consumer reads from the stream per iteration before firing `RedisBufferConsumeEvent`. |

## Health Check

Run the following command to verify that Spool is correctly set up in your environment:

```bash
php artisan spool:health
```

It checks:

- The `active/`, `processing/`, and `completed/` buffer directories exist and are writable
- All config values are valid (correct types, sensible ranges, no conflicting values)

```bash
# Everything is fine
All checks passed.

# Problems found
ERROR  Buffer 'active' directory does not exist: .../storage/app/private/buffer/active
ERROR  max_flush_shards (10) cannot exceed max_shards (5).
```

The command exits with code `0` on success and `1` on failure, so it can be wired into deployment pipelines or Docker health checks.

## Suggested Scheduled Setup (Filesystem Driver)

When using the filesystem driver, add flush and clean calls to your schedule in `routes/console.php`:

```php
use Alihaiderx\LaravelSpool\Facades\FileSystemBuffer;

Schedule::call(function () {
    FileSystemBuffer::flush(function (string $file, ?string $bucket): bool {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $rows  = array_map(fn ($line) => unserialize($line), $lines);

        // Process $rows...

        return true;
    }, 'page-views');
})->everyMinute();

Schedule::call(function () {
    FileSystemBuffer::clean(50, 'page-views');
})->daily();
```

## Performance Metrics

> Coming soon. Benchmarks comparing buffered vs. direct writes across different load profiles will be published here.

## License

Laravel Spool is open-source software released under the [MIT license](https://opensource.org/licenses/MIT).
