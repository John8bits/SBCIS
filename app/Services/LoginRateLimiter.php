<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class LoginRateLimiter
{
    private const WINDOW_SECONDS = 900;
    private const FREE_FAILURES = 5;
    private const MAX_BACKOFF_SECONDS = 900;

    public function __construct(private PDO $database)
    {
    }

    public function assertAllowed(string $remoteAddress): void
    {
        $statement = $this->database->prepare(
            'SELECT blocked_until FROM login_attempts WHERE attempt_key=:attempt_key LIMIT 1'
        );
        $statement->execute([':attempt_key' => $this->key($remoteAddress)]);
        $blockedUntil = $statement->fetchColumn();
        if ($blockedUntil !== false && strtotime((string) $blockedUntil) > time()) {
            throw new RuntimeException('Too many sign-in attempts. Please wait and try again.');
        }
    }

    public function recordFailure(string $remoteAddress): void
    {
        $key = $this->key($remoteAddress);
        $this->database->beginTransaction();
        try {
            $insert = $this->database->prepare(
                'INSERT IGNORE INTO login_attempts
                    (attempt_key, failure_count, first_failed_at, last_failed_at)
                 VALUES (:attempt_key, 0, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $insert->execute([':attempt_key' => $key]);

            $select = $this->database->prepare(
                'SELECT failure_count, first_failed_at FROM login_attempts
                 WHERE attempt_key=:attempt_key FOR UPDATE'
            );
            $select->execute([':attempt_key' => $key]);
            $attempt = $select->fetch(PDO::FETCH_ASSOC);
            $expired = !$attempt || strtotime((string) $attempt['first_failed_at']) < time() - self::WINDOW_SECONDS;
            $failures = $expired ? 1 : (int) $attempt['failure_count'] + 1;
            $backoff = $failures < self::FREE_FAILURES
                ? 0
                : min(self::MAX_BACKOFF_SECONDS, 5 * (2 ** min(7, $failures - self::FREE_FAILURES)));

            $update = $this->database->prepare(
                'UPDATE login_attempts SET failure_count=:failure_count,
                    first_failed_at=IF(:expired=1, UTC_TIMESTAMP(), first_failed_at),
                    last_failed_at=UTC_TIMESTAMP(),
                    blocked_until=IF(:backoff_zero=0, NULL, DATE_ADD(UTC_TIMESTAMP(), INTERVAL :backoff_seconds SECOND))
                 WHERE attempt_key=:attempt_key'
            );
            $update->bindValue(':failure_count', $failures, PDO::PARAM_INT);
            $update->bindValue(':expired', $expired ? 1 : 0, PDO::PARAM_INT);
            $update->bindValue(':backoff_zero', $backoff, PDO::PARAM_INT);
            $update->bindValue(':backoff_seconds', $backoff, PDO::PARAM_INT);
            $update->bindValue(':attempt_key', $key);
            $update->execute();
            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) $this->database->rollBack();
            throw $error;
        }
    }

    public function clear(string $remoteAddress): void
    {
        $statement = $this->database->prepare('DELETE FROM login_attempts WHERE attempt_key=:attempt_key');
        $statement->execute([':attempt_key' => $this->key($remoteAddress)]);
    }

    private function key(string $remoteAddress): string
    {
        return hash('sha256', trim($remoteAddress) !== '' ? trim($remoteAddress) : 'unknown');
    }
}
