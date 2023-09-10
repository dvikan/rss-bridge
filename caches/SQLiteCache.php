<?php

declare(strict_types=1);

class SQLiteCache implements CacheInterface
{
    private \SQLite3 $db;
    private array $config;

    public function __construct(array $config)
    {
        $default = [
            'file'          => null,
            'timeout'       => 5000,
            'enable_purge'  => true,
        ];
        $config = array_merge($default, $config);
        $this->config = $config;

        if (!$config['file']) {
            throw new \Exception('sqlite cache needs a file');
        }

        if (is_file($config['file'])) {
            $this->db = new \SQLite3($config['file']);
            $this->db->enableExceptions(true);
        } else {
            // Create the file and create sql schema
            $this->db = new \SQLite3($config['file']);
            $this->db->enableExceptions(true);
            $this->db->exec("CREATE TABLE storage ('key' BLOB PRIMARY KEY, 'value' BLOB, 'updated' INTEGER)");
        }
        $this->db->busyTimeout($config['timeout']);
        // https://www.sqlite.org/pragma.html#pragma_journal_mode
        $this->db->exec('PRAGMA journal_mode = wal');
        // https://www.sqlite.org/pragma.html#pragma_synchronous
        $this->db->exec('PRAGMA synchronous = NORMAL');
    }

    public function get(string $key, $default = null)
    {
        $cacheKey = $this->createCacheKey($key);
        $stmt = $this->db->prepare('SELECT value, updated FROM storage WHERE key = :key');
        $stmt->bindValue(':key', $cacheKey);
        $result = $stmt->execute();
        if (!$result) {
            return $default;
        }
        $row = $result->fetchArray(\SQLITE3_ASSOC);
        if ($row === false) {
            return $default;
        }
        $expiration = $row['updated'];
        if ($expiration === 0 || $expiration > time()) {
            $blob = $row['value'];
            $value = unserialize($blob);
            if ($value === false) {
                Logger::error(sprintf("Failed to unserialize: '%s'", mb_substr($blob, 0, 100)));
                // delete?
                return $default;
            }
            return $value;
        }
        // delete?
        return $default;
    }
    public function set(string $key, $value, int $ttl = null): void
    {
        $cacheKey = $this->createCacheKey($key);
        $blob = serialize($value);
        $expiration = $ttl === null ? 0 : time() + $ttl;
        $stmt = $this->db->prepare('INSERT OR REPLACE INTO storage (key, value, updated) VALUES (:key, :value, :updated)');
        $stmt->bindValue(':key', $cacheKey);
        $stmt->bindValue(':value', $blob, \SQLITE3_BLOB);
        $stmt->bindValue(':updated', $expiration);
        $result = $stmt->execute();
        // Unclear whether we should $result->finalize(); here?
    }

    public function delete(string $key): void
    {
        $key = $this->createCacheKey($key);
        $stmt = $this->db->prepare('DELETE FROM storage WHERE key = :key');
        $stmt->bindValue(':key', $key);
        $result = $stmt->execute();
    }

    public function prune(): void
    {
        if (!$this->config['enable_purge']) {
            return;
        }
        $stmt = $this->db->prepare('DELETE FROM storage WHERE updated <= :now');
        $stmt->bindValue(':now', time());
        $result = $stmt->execute();
    }

    public function clear(): void
    {
        $this->db->query('DELETE FROM storage');
    }

    private function createCacheKey($key)
    {
        return hash('sha1', $key, true);
    }
}
