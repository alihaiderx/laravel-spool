<?php

return [
  'max_shards' => env('SPOOL_MAX_SHARDS', 30),
  'max_shard_size' => env('SPOOL_MAX_SHARD_SIZE', 300 * 1024),
  'max_flush_shards' => env('SPOOL_MAX_FLUSH_SHARDS', 5),
  'clean_shards_in_days' => env('SPOOL_CLEAN_SHARDS_IN_DAYS', 3)
];
