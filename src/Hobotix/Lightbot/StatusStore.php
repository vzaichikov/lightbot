<?php

namespace Hobotix\Lightbot;

use DateTimeImmutable;

interface StatusStore
{
    public function fetchStatus(string $domain): ?array;
    public function insertInitialStatus(string $domain, int $status, DateTimeImmutable $now): void;
    public function updateChecked(string $domain, ?int $pendingStatus, DateTimeImmutable $now): void;
    public function confirmStatusChange(string $domain, int $status, DateTimeImmutable $now): void;
}
