<?php

namespace Alihaiderx\LaravelSpool\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Throwable;

class FileSystemBufferService
{

  function getDirs()
  {
    return [
      'buffer' => 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'buffer',
      'bufferActive' => 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'buffer' . DIRECTORY_SEPARATOR . 'active',
      'bufferProcessing' => 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'buffer' . DIRECTORY_SEPARATOR . 'processing',
      'bufferCompleted' => 'app' . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'buffer' . DIRECTORY_SEPARATOR . 'completed',
    ];
  }

  function setupDirs()
  {
    $dirs = $this->getDirs();

    if (!file_exists(storage_path($dirs['buffer']))) {
      mkdir(storage_path($dirs['buffer']), 0774, true);
      chmod(storage_path($dirs['buffer']), 0774);
    }
    if (!file_exists(storage_path($dirs['bufferActive']))) {
      mkdir(storage_path($dirs['bufferActive']), 0774, true);
      chmod(storage_path($dirs['bufferActive']), 0774);
    }
    if (!file_exists(storage_path($dirs['bufferProcessing']))) {
      mkdir(storage_path($dirs['bufferProcessing']), 0774, true);
      chmod(storage_path($dirs['bufferProcessing']), 0774);
    }
    if (!file_exists(storage_path($dirs['bufferCompleted']))) {
      mkdir(storage_path($dirs['bufferCompleted']), 0774, true);
      chmod(storage_path($dirs['bufferCompleted']), 0774);
    }
  }

  // Buffer

  function buffer(array $arr, string $bucketSlug): string|null
  {

    try {

      $spoolConfig = config('spoolx');
      $maxShards = $spoolConfig['max_shards'];
      $shardSize = $spoolConfig['max_shard_size'];

      $payload = serialize($arr['payload'] ?? '');
      $shard = abs(crc32($payload)) % $maxShards;

      $file = storage_path($this->generateSharedFileName($bucketSlug, $shard));
      $line = $payload . PHP_EOL;
      file_put_contents($file, $line, FILE_APPEND | LOCK_EX);

      if (file_exists($file) && filesize($file) > $shardSize) {
        $newName = str_replace('.log', '-' . time() . '.log', $file);
        rename($file, $newName);
      }

      return $file;
    } catch (Throwable $e) {
      Log::error($e->getMessage(), ['trace'=>$e->getTraceAsString()]);
      return NULL;
    }
  }

  function generateSharedFileName(string $bucketSlug, string $shard, string $type = 'bufferActive'): string
  {
    $dirs = $this->getDirs();
    $dir = $dirs[$type];
    return $dir . DIRECTORY_SEPARATOR . $bucketSlug . '-' . date('Y-m-d') . '-' . $shard . '.log';
  }

  // Flush

  function flush(callable $callback, ?string $bucketSlug = null): bool|null
  {
    $spoolConfig = config('spool');
    $dirs = $this->getDirs();
    $bufferActiveDir = $dirs['bufferActive'];

    if (empty($bucketSlug)) $files = glob(storage_path($bufferActiveDir . DIRECTORY_SEPARATOR . '*.log'));
    else $files = glob(storage_path($bufferActiveDir . DIRECTORY_SEPARATOR . $bucketSlug . '-*.log'));

    if (count($files) === 0) return null;
    $files = array_slice($files, 0, $spoolConfig['max_flush_shards']);

    foreach ($files as $file) {

      $processingFile = str_replace('buffer' . DIRECTORY_SEPARATOR . 'active', 'buffer' . DIRECTORY_SEPARATOR . 'processing', $file);
      if (!rename($file, $processingFile)) continue;

      $processedFileBool = $callback($processingFile, $bucketSlug);

      if (!empty($processedFileBool)) {
        $completedFile = str_replace('buffer' . DIRECTORY_SEPARATOR . 'processing', 'buffer' . DIRECTORY_SEPARATOR . 'completed', $processingFile);
        rename($processingFile, $completedFile);
      }
    }

    return true;
  }

  // Clean

  function clean(int $numberOfFiles = 10, ?string $bucketSlug = NULL): array
  {
    $spoolConfig = config('spool');
    $dirs = $this->getDirs();
    $bufferCompletedDir = $dirs['bufferCompleted'];
    $cleanedFiles = [];

    $maxFileAge = $spoolConfig['shards_ttl_days'];

    if (empty($bucketSlug)) {
      $files = glob(storage_path($bufferCompletedDir . DIRECTORY_SEPARATOR . '*.log'));
    } else {
      $files = glob(storage_path($bufferCompletedDir . DIRECTORY_SEPARATOR . $bucketSlug . '-*.log'));
    }

    $files = array_splice($files, 0, $numberOfFiles);

    foreach ($files as $file) {

      $claim = $file . '.deleting';

      $fileAge = $this->getShardAgeInDays($file);
      if ($fileAge === NULL) continue;

      if ($fileAge < ($maxFileAge) * -1) {
        if (rename($file, $claim)) {
          unlink($claim);
          $cleanedFiles[] = $file;
        }
      }
    }

    return $cleanedFiles;
  }

  function getShardAgeInDays(string $file)
  {

    try {
      $date = NULL;

      if (preg_match('/\d{4}-\d{2}-\d{2}/', $file, $matches)) {
        $date = Carbon::createFromFormat('Y-m-d', $matches[0])->startOfDay();
        return now()->startOfDay()->diffInDays($date, false);
      }

      return NULL;
    } catch (Exception $e) {
      return NULL;
    }
  }
}
