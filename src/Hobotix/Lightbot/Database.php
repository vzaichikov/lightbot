<?php

namespace Hobotix\Lightbot;

use DateTimeImmutable;
use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = getenv('LIGHTBOT_DB_DSN');
        if ($dsn === false) {
            $charset = $config['charset'] ?? 'utf8mb4';
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['database'],
                $charset
            );
        }

        $user = getenv('LIGHTBOT_DB_USER');
        if ($user === false) {
            $user = $config['user'] ?? null;
        }

        $password = getenv('LIGHTBOT_DB_PASSWORD');
        if ($password === false) {
            $password = $config['password'] ?? null;
        }

        $this->pdo = new PDO(
            $dsn,
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
    }

    public function fetchStatus(string $domain): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT domain, status, pending_status, last_change_at, last_checked_at
             FROM device_status
             WHERE domain = :domain'
        );
        $stmt->execute(['domain' => $domain]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function insertInitialStatus(string $domain, int $status, DateTimeImmutable $now): void
    {
        $timestamp = $now->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO device_status (domain, status, pending_status, last_change_at, last_checked_at)
             VALUES (:domain, :status, NULL, :last_change_at, :last_checked_at)'
        );
        $stmt->execute([
            'domain' => $domain,
            'status' => $status,
            'last_change_at' => $timestamp,
            'last_checked_at' => $timestamp,
        ]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO status_events (domain, status, started_at, ended_at)
             VALUES (:domain, :status, :started_at, NULL)'
        );
        $stmt->execute([
            'domain' => $domain,
            'status' => $status,
            'started_at' => $timestamp,
        ]);
    }

    public function updateChecked(string $domain, ?int $pendingStatus, DateTimeImmutable $now): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE device_status
             SET pending_status = :pending_status,
                 last_checked_at = :last_checked_at
             WHERE domain = :domain'
        );
        $stmt->bindValue('pending_status', $pendingStatus, $pendingStatus === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue('last_checked_at', $now->format('Y-m-d H:i:s'));
        $stmt->bindValue('domain', $domain);
        $stmt->execute();
    }

    public function confirmStatusChange(string $domain, int $status, DateTimeImmutable $now): void
    {
        $timestamp = $now->format('Y-m-d H:i:s');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE device_status
                 SET status = :status,
                     pending_status = NULL,
                     last_change_at = :last_change_at,
                     last_checked_at = :last_checked_at
                 WHERE domain = :domain'
            );
            $stmt->execute([
                'status' => $status,
                'last_change_at' => $timestamp,
                'last_checked_at' => $timestamp,
                'domain' => $domain,
            ]);

            $stmt = $this->pdo->prepare(
                'SELECT id
                 FROM status_events
                 WHERE domain = :domain AND ended_at IS NULL
                 ORDER BY started_at DESC
                 LIMIT 1'
            );
            $stmt->execute(['domain' => $domain]);
            $eventId = $stmt->fetchColumn();

            if ($eventId) {
                $stmt = $this->pdo->prepare(
                    'UPDATE status_events
                     SET ended_at = :ended_at
                     WHERE id = :id'
                );
                $stmt->execute([
                    'ended_at' => $timestamp,
                    'id' => $eventId,
                ]);
            }

            $stmt = $this->pdo->prepare(
                'INSERT INTO status_events (domain, status, started_at, ended_at)
                 VALUES (:domain, :status, :started_at, NULL)'
            );
            $stmt->execute([
                'domain' => $domain,
                'status' => $status,
                'started_at' => $timestamp,
            ]);

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function fetchRecentEvents(string $domain, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT status, started_at, ended_at
             FROM status_events
             WHERE domain = :domain
             ORDER BY started_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue('domain', $domain);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
