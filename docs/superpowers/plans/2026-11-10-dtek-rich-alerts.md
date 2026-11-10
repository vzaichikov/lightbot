# DTEK Rich Alerts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver DTEK-enriched power alerts and a 09:00 Kyiv daily digest to the two production Telegram groups.

**Architecture:** PHP schedule normalization and private atomic file cache feed a native-rich-message composer. A bounded Telegram transport serves existing event monitoring and a date-keyed morning worker. HestiaCP owns production cron jobs.

**Tech Stack:** PHP 8.3+, PDO MySQL, cURL, Composer autoload; dependency-free PHP tests.

**Spec:** `docs/superpowers/specs/2026-11-10-dtek-rich-alerts-design.md`

## Global Constraints

- Approved digest time: 09:00 Europe/Kyiv, catch-up until 11:55; HestiaCP native cron only.
- Existing groups; `sendRichMessage` with basic HTML fallback only for definite unsupported-format rejection.
- Hobotix: kyiv-region/GPV5.2; Charmpole: kyiv/GPV14.1, Берестейський 56А.
- Mini Mon read-only; no development schedules; no schema migrations; preserve credentials.
- Feed cache 5 minutes; stale after 20 minutes; reject timestamps over 1 minute ahead.
- Every existing phrase collection grows to 25 unique variants.

## Review Focus

- Fresh mirror timestamp with malformed/incomplete group data must not produce invented restoration times (Task 1).
- DST, cross-midnight and schedule/observation disagreement must preserve real uncertainty (Task 1).
- API timeout after dispatch must not trigger fallback or a duplicate morning send (Tasks 2–3).
- Corrupt delivery state or unwritable private storage must fail closed before delivery (Task 3).
- Rich API failure must not suppress state confirmation or subsequent domains (Task 3).

### Task 1: Schedule and private storage

**Files:** `Schedule.php`, `ScheduleCache.php`, `RuntimeStore.php` under `src/Hobotix/Lightbot/`; `tests/bootstrap.php`, `tests/schedule.php`.

**Interfaces:** `Schedule::normalize(array $raw, string $region, string $group): array`; `Schedule::forecast(array $snapshot, bool $on, DateTimeImmutable $now): string`; `Schedule::day(array $snapshot, DateTimeImmutable $now): array`; `ScheduleCache::__construct(RuntimeStore $store, ?Closure $fetch=null)` with `refresh(array $dtek, DateTimeImmutable $now): array` and `read(array $dtek, DateTimeImmutable $now): array`. Snapshot has `data`, `stale`, `error`, `checked_at`; normalized data has slots `{start,end,state}` in Unix seconds, source and update metadata.

- [ ] Write tests asserting exact half-hour transitions; DST days contain 46/50 half-hours; missing/unknown hours reject; midnight without tomorrow and OFF-to-possible never promise restoration; mismatches explicitly reported; stale/future/collector failures suppress forecasts; failed refresh retains stale prior data; bad cache is unavailable.
- [ ] Run `php tests/schedule.php`: expect missing implementation failures.
- [ ] Implement validated normalization, conservative query logic, atomic private storage and bounded HTTPS cache refresh.
- [ ] Run `php tests/schedule.php`: expect all PASS; commit task.

### Task 2: Native message composition, copy and transport

**Files:** `MessageComposer.php`, `TelegramClient.php`, `Texts.php`; `tests/messages.php`, `tests/telegram.php`.

**Interfaces:** `MessageComposer::event(string $domain,array $config,bool $on,int $hours,int $minutes,array $snapshot,DateTimeImmutable $now): array`; `digest(string $domain,array $config,array $snapshot,DateTimeImmutable $now): array`; both return `rich` and `basic` HTML strings. `TelegramClient::send(string $token,string $chat,array $message): array` returns `status` (sent/rejected/uncertain), `retry_after` and redacted `code`.

- [ ] Write tests for explicit status, duration, next plan, daily tables/unknown day, escaping, per-domain groups, phrase uniqueness/counts; transport tests for success, explicit unsupported-rich fallback, rate limit, HTTP/API error, invalid response and timeout without fallback.
- [ ] Run `php tests/messages.php` and `php tests/telegram.php`: expect failures.
- [ ] Implement supported rich HTML plus basic fallback and fixed TLS/timeout checked transport; expand copy to 25 entries per existing collection.
- [ ] Run both tests: expect all PASS; commit task.

### Task 3: Morning worker and monitoring integration

**Files:** `MorningDigest.php`, `StatusStore.php`, `Database.php`, `LightBot.php`, `Config.php`, `run.php`, `schedule.php`, `config.example.php`; `tests/morning.php`, `tests/monitoring.php`, `tests/run.php`, `tools/preview-messages.php`.

**Interfaces:** `MorningDigest::run(array $domains,DateTimeImmutable $now): array` with injected store/cache/composer/transport. `StatusStore` exposes current Database transition methods; Database implements it. LightBot accepts store/transport/cache dependencies, reads schedules only on confirmed events and returns existing statuses.

- [ ] Write tests for 09:00 Kyiv gating, date isolation, duplicate suppression, bounded definite-failure retries, ambiguous result suppression, corrupt storage, disabled credentials and domain isolation. Exercise real debounce through an in-memory StatusStore and failing injected transport.
- [ ] Run morning/monitoring tests: expect missing-feature failures.
- [ ] Implement morning lock+intent state, schedule CLI, non-overlapping runner, config and integration. Catch notification errors without blocking confirmed state or other domains.
- [ ] Run `php tests/run.php`, lint changed PHP, generate fixture-only previews; expect all PASS; commit task.

### Task 4: Review and production release

**Files:** `docs/operations.md`, approved spec/plan; operational evidence in locally excluded `.codex`.

- [ ] Document runtime directory, timezone, Hestia commands, delivery limits and rollback; review whole branch with a fresh reviewer, fix important findings with regression tests.
- [ ] Run full tests and PHP lint; expect all PASS, clean diff. Commit final release.
- [ ] Recheck exact production owner/root/master/clean baseline; back up private config and Hestia cron metadata privately. Publish release to origin/master and deploy exact SHA by fast-forward.
- [ ] Apply additive private config and runtime directory; prime cache without sending. Add jobs through Hestia native CLI, preserving unrelated jobs and existing monitoring job.
- [ ] Verify release SHA, clean tree, PHP lint/autoload, DB read-only access, Hestia job definitions and fresh data for both groups. Do not execute live monitoring for verification.

## Execution authorization

The user explicitly requested proceeding with implementation, testing and deployment after approving the design. Execute this plan inline without an additional plan-approval gate. Use the existing clean feature checkout; no new worktree is needed.
