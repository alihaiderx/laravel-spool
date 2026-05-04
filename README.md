<p align="center">
  <img src="art/logo.png" alt="Laravel Spool" width="350">
</p>

<h1 align="center">Laravel Spool</h1>

<p align="center">
  <strong>Batch and defer expensive database writes in Laravel — no Redis, no queues, no background workers required.</strong><br>
  Works on shared hosting. Drop 1,000 individual DB inserts down to a single batch operation.
</p>

<p align="center">
  <a href="https://packagist.org/packages/alihaiderx/laravel-spool">
    <img src="https://img.shields.io/packagist/v/alihaiderx/laravel-spool" alt="Latest Version">
  </a>
  <a href="https://packagist.org/packages/alihaiderx/laravel-spool">
    <img src="https://img.shields.io/packagist/dt/alihaiderx/laravel-spool" alt="Total Downloads">
  </a>
  <a href="https://github.com/alihaiderx/laravel-spool/stargazers">
    <img src="https://img.shields.io/github/stars/alihaiderx/laravel-spool?style=flat" alt="GitHub Stars">
  </a>
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

---

## The Problem

Every time a user visits a page, triggers an event, or hits your API, your Laravel app likely writes to the database. Once. Per event. Every time.

Under normal traffic that's fine. Under load it becomes a bottleneck: lock contention, slow response times, and a database that can't keep up.

The standard fix — Laravel queues, Redis, Horizon, Supervisor — adds real infrastructure complexity. And if you're on **shared hosting**, those options simply aren't available.

---

## The Solution

Laravel Spool captures high-frequency writes to a **fast local buffer first**, then processes them as a single batch on a schedule you control.

- **1,000 individual inserts → 1 batch insert**
- **No Redis required** — the filesystem driver works anywhere PHP runs
- **No background workers** — flush runs as a scheduled Laravel task
- **No configuration overhead** — one install command and you're buffering

It's a lightweight performance layer that fits between your application events and your database.

---

## Key Features

| Feature | Why It Matters |
|---|---|
| **Filesystem buffering** | Works on any host — no Redis, no extensions, no extra services |
| **Sharded writes** | Spreads data across multiple files to avoid write contention under load |
| **Atomic processing** | File rename guarantees no two workers process the same shard |
| **Three-stage lifecycle** | `active → processing → completed` gives you visibility and auditability |
| **Multiple buckets** | Separate buffers per data type (`page-views`, `api-logs`, `metrics`) |
| **Redis Streams driver** | Optional upgrade path when your infra supports it |
| **Health check command** | Verify your setup is correct before going to production |
| **TTL-based cleanup** | Completed shards auto-expire — no manual disk management |

---

## Use Cases

- **Page view / analytics tracking** — buffer every hit, insert hourly in bulk
- **Activity logs** — accumulate user actions, write in batches instead of per-action
- **API usage metering** — count calls without a DB write on every request
- **Bulk form submissions** — queue submissions to a buffer, process on a schedule
- **Shared hosting optimization** — get performance gains without Redis or queue workers

---

## Quick Start

### 1. Install

```bash
composer require alihaiderx/laravel-spool
```

Laravel auto-discovers the service provider. No manual registration needed.

### 2. Set Up

```bash
php artisan spool:install
```

This publishes `config/spool.php` and creates the buffer directories under `storage/app/private/buffer/`.

### 3. Buffer Data

```php
use Alihaiderx\LaravelSpool\Facades\Buffer;

// Call this on every page view, event, API hit, etc.
Buffer::buffer([
    'payload' => [
        'user_id' => $user->id,
        'event'   => 'page_view',
        'url'     => request()->path(),
    ]
], 'page-views');
```

### 4. Flush in Batches

In `routes/console.php`, schedule a flush:

```php
use Alihaiderx\LaravelSpool\Facades\FileSystemBuffer;

Schedule::call(function () {
    FileSystemBuffer::flush(function (string $file, ?string $bucket): bool {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $rows  = array_map(fn ($line) => unserialize($line), $lines);

        // One DB insert for potentially thousands of events
        DB::table('page_views')->insert($rows);

        return true;
    }, 'page-views');
})->everyMinute();

Schedule::call(function () {
    FileSystemBuffer::clean(50, 'page-views');
})->daily();
```

That's it. Your app now buffers writes and processes them in batches.

---

## How It Works

### Filesystem Driver (Default)

Every call to `Buffer::buffer()` serializes the payload and appends it to a shard file. Writes are distributed across up to `max_shards` files using a hash, which reduces file-level lock contention when multiple requests write simultaneously.

When `flush()` runs:

1. Shard files in `active/` are **atomically renamed** to `processing/` — this is a single OS-level rename, so two concurrent flush jobs can never pick up the same shard.
2. Your callback receives each shard file path. You read the lines, process them however you need, and return `true`.
3. Processed shards move to `completed/` and are held for `shards_ttl_days` days before `clean()` removes them.

```
storage/app/private/buffer/
├── active/       ← new payloads are written here
├── processing/   ← shards claimed by a flush job
└── completed/    ← successfully processed, kept for audit
```

### Redis Streams Driver (Optional)

When Redis is available, set `SPOOL_BUFFER_DRIVER=redis`. The package writes to a Redis Stream via `XADD`. A long-running consumer process reads batches from the stream and fires a `RedisBufferConsumeEvent` that your application handles.

```bash
php artisan spool:start-redis-consume
```

---

## Why Not Just Use Laravel Queues?

| | Laravel Spool | Laravel Queues |
|---|---|---|
| **Works on shared hosting** | Yes | Usually not |
| **Requires Redis** | No (filesystem driver) | Often yes |
| **Requires Supervisor** | No | Yes, for reliability |
| **Best for** | Batching identical writes | Individual background jobs |
| **Processing model** | Scheduled batch flush | Per-job async |
| **Infrastructure overhead** | Minimal | Queue worker + process monitor |

**Use Spool when** you need to batch many similar writes (analytics, logs, counters) and want zero extra infrastructure.

**Use Laravel queues when** you need per-job async processing, retries, failed job handling, or complex background workflows.

They are not mutually exclusive — many apps use both.

---

## Performance Impact

**Without Spool** — direct writes on every event:

```
1,000 page views → 1,000 individual INSERT statements
Response time per request: ~15–40ms (DB write cost added)
DB load: constant, spiky, high under traffic
```

**With Spool** — buffered and batched:

```
1,000 page views → buffer to disk (microseconds per write)
Scheduled flush → 1 batch INSERT of 1,000 rows
Response time per request: no DB write overhead
DB load: predictable, batched, low
```

The write cost moves off your HTTP response cycle entirely.

---

## Shared Hosting Advantage

Most Laravel performance packages assume you have Redis, a queue worker, and Supervisor. On shared hosting, you have none of those.

Laravel Spool's filesystem driver removes those requirements entirely:

- **No Redis** — buffers to local disk files
- **No background workers** — flushing runs via Laravel's scheduler (a single cron entry)
- **No Supervisor** — nothing to keep alive
- **No extra dependencies** — just PHP and a writable filesystem

The only thing required is the standard Laravel scheduler cron entry in your cPanel or server cron:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

That's standard Laravel. If your app already runs on shared hosting, Spool works immediately.

---

## Configuration

Published to `config/spool.php` after running `spool:install`.

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

| Variable | Default | Description |
|---|---|---|
| `SPOOL_BUFFER_DRIVER` | `file` | `file` or `redis` |
| `SPOOL_MAX_SHARDS` | `30` | Number of shard files per bucket. More shards = less write contention. |
| `SPOOL_MAX_SHARD_SIZE` | `307200` (300 KB) | Shard file size limit before rotation. |
| `SPOOL_MAX_FLUSH_SHARDS` | `5` | Max shards processed per `flush()` call. Keeps jobs short. |
| `SPOOL_SHARDS_TTL_DAYS` | `3` | Days to retain completed shards before deletion. |
| `SPOOL_REDIS_BATCH_SIZE` | `500` | Messages read per Redis consumer iteration. |

---

## Health Check

Before deploying, verify your setup:

```bash
php artisan spool:health
```

This checks that buffer directories exist and are writable, and that all config values are valid.

```
All checks passed.
```

```
ERROR  Buffer 'active' directory does not exist: .../storage/app/private/buffer/active
ERROR  max_flush_shards (10) cannot exceed max_shards (5).
```

The command exits with code `0` on success and `1` on failure — safe to use in deployment pipelines and Docker health checks.

---

## Redis Driver Setup (Optional)

If your infrastructure supports Redis and you want lower write latency:

**1.** Install predis if not using the `phpredis` extension:

```bash
composer require predis/predis
```

**2.** Set the driver:

```dotenv
SPOOL_BUFFER_DRIVER=redis
```

**3.** Start the consumer (manage with Supervisor in production):

```bash
php artisan spool:start-redis-consume
```

**4.** Listen for batched events in your application:

```php
use Alihaiderx\LaravelSpool\Facades\Buffer;
use Alihaiderx\LaravelSpool\Events\RedisBufferConsumeEvent;

Buffer::listenRedis(function (RedisBufferConsumeEvent $event) {
    $pageViews = array_filter(
        $event->batch,
        fn ($item) => $item['bucketSlug'] === 'page-views'
    );

    DB::table('page_views')->insert(array_column($pageViews, 'payload'));
});
```

---

## Requirements

- PHP 8.2+
- Laravel 12.x
- Redis driver: `phpredis` extension or `predis/predis`

---

## Roadmap

- [ ] Dashboard UI for monitoring buffer state
- [ ] Artisan command to manually trigger a flush
- [ ] Laravel 11.x support
- [ ] Benchmarks and performance comparison guide

---

## Contributing

Pull requests are welcome. For significant changes, open an issue first to discuss what you'd like to change.

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/your-feature`)
3. Commit your changes
4. Open a pull request

---

## License

Laravel Spool is open-source software released under the [MIT license](https://opensource.org/licenses/MIT).
