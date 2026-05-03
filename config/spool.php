<?php

return [
  'buffer_driver' => env('SPOOL_BUFFER_DRIVER', 'file'),
  'max_shards' => env('SPOOL_MAX_SHARDS', 30),
  'max_shard_size' => env('SPOOL_MAX_SHARD_SIZE', 307200),
  'max_flush_shards' => env('SPOOL_MAX_FLUSH_SHARDS', 5),
  'shards_ttl_days' => env('SPOOL_SHARDS_TTL_DAYS', 3),
  'redis_batch_size' => env('SPOOL_REDIS_BATCH_SIZE', 500)
];
