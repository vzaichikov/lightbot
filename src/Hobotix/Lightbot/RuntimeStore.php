<?php

namespace Hobotix\Lightbot;

/** Private, app-owned files. Callers hold a lock across read/modify/write. */
final class RuntimeStore
{
    public function __construct(private string $directory)
    {
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create runtime directory');
        }
        if (!is_writable($directory)) {
            throw new \RuntimeException('Runtime directory is not writable');
        }
    }

    private function path(string $key, string $suffix): string
    {
        if (!preg_match('/\A[a-zA-Z0-9_.-]+\z/', $key)) {
            throw new \InvalidArgumentException('Invalid runtime key');
        }
        return $this->directory . '/' . $key . $suffix;
    }

    public function read(string $key): ?array
    {
        $path = $this->path($key, '.json');
        if (!file_exists($path)) {
            return null;
        }
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException('Cannot read runtime state');
        }
        $data = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid runtime state');
        }
        return $data;
    }

    public function write(string $key, array $data): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $path = $this->path($key, '.json');
        $temp = $this->path($key, '.' . bin2hex(random_bytes(8)) . '.tmp');
        $oldMask = umask(0077);
        try {
            if (@file_put_contents($temp, $json, LOCK_EX) !== strlen($json) || !@rename($temp, $path)) {
                throw new \RuntimeException('Cannot persist runtime state');
            }
        } finally {
            if (file_exists($temp)) {
                @unlink($temp);
            }
            umask($oldMask);
        }
    }

    /** @return resource|null Nonblocking lock; keep the handle alive while working. */
    public function lock(string $key)
    {
        $oldMask = umask(0077);
        try {
            $handle = @fopen($this->path($key, '.lock'), 'c');
        } finally {
            umask($oldMask);
        }
        if ($handle === false) {
            throw new \RuntimeException('Cannot open runtime lock');
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }
        return $handle;
    }
}
