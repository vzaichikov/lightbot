# DTEK schedules and Telegram alerts

LightBot requires PHP 8.3+, cURL, PDO MySQL and the existing Composer autoloader. No new Composer packages or database migrations are required. Scheduled commands are CLI-only. The dashboard remains read-only.

## Configuration

Keep credentials in ignored `config.php`. Per-domain additions are shown in `config.example.php`: `label`, and `dtek.region`, `dtek.group`, `dtek.address`. Kyiv city and Kyiv Oblast are different feeds. Charmpole's GPV14.1 mapping uses Берестейський 56А by the owner's explicit instruction; it does not independently establish the supply serving plain 56.

`runtime_dir` (or `LIGHTBOT_RUNTIME_DIR`) selects private writable storage. The default is `.lightbot-runtime` in the parent of the checkout. Production uses `/home/hobotix/web/light.hobotix.dev/.lightbot-runtime`, owned by hobotix, mode 0700. Keep it outside the webroot and out of Git. Cache, lock, digest and manual-verification records are mode 0600. Delivery records must be preserved across deployments; deleting them can permit repeat sends.

## HestiaCP scheduling

Use Hestia's CLI as an administrator. Do not edit the user's crontab directly: Hestia owns its metadata and rebuilds the effective crontab. Inspect existing entries before adding anything:

```sh
sudo /usr/local/hestia/bin/v-list-cron-jobs hobotix json
timedatectl show --property=Timezone --value
```

The production server timezone was verified as Europe/Kyiv. Preserve the existing minute monitoring entry and every unrelated/suspended entry. Add these only if absent:

```sh
sudo /usr/local/hestia/bin/v-add-cron-job hobotix '*/5' '*' '*' '*' '*' 'php /home/hobotix/web/light.hobotix.dev/public_html/schedule.php refresh'
sudo /usr/local/hestia/bin/v-add-cron-job hobotix '*/5' '9-11' '*' '*' '*' 'php /home/hobotix/web/light.hobotix.dev/public_html/schedule.php morning'
```

The digest starts at **09:00 Europe/Kyiv**. The 09:00–11:55 window allows catch-up after downtime and up to three definite-rejection attempts, respecting Telegram retry_after. The worker also gates by Kyiv time internally. If the server timezone changes, update Hestia hours to match or run the worker every five minutes all day and retain its internal gate. Install no recurring tasks on development or Windows.

`run.php` holds a nonblocking lock across probing and confirming transitions. Event messages read the existing cache only; DTEK fetches cannot delay monitoring. The refresh job fetches each configured region/group with bounded HTTPS requests. The morning worker refreshes before its digest.

## Data and message semantics

The source is DTEK via `Baskerville42/outage-data-ua` on GitHub. Published daily `fact.data` is used; weekly presets never stand in for an unpublished day. Hourly states are interpreted as half-hours. Kyiv DST, gaps, midnight and possible-outage states are handled explicitly. Cache data older than 20 minutes, collector errors or timestamps more than one minute ahead suppress confident forecasts.

If observed OFF contradicts planned ON, the bot says the return time is unknown. An OFF-to-possible boundary and an unpublished tomorrow are never reported as confirmed restoration times. An all-ON published day differs from unavailable data. Missing data generates an honest morning digest once; this release does not send later schedule-change corrections.

Telegram sends native rich HTML with a basic HTML fallback only after an explicit unsupported-rich-format rejection. Arbitrary CSS/HTML widgets are not used: current production widgets are private-chat only, while these destinations are groups. TLS verification, request timeouts and API `ok` checks are enabled. Logs and receipts omit bot tokens and raw Telegram errors.

## Delivery limits

Morning delivery writes intent before dispatch and the outcome afterward under a domain/date lock. Sent, uncertain or interrupted sends are never automatically repeated. This favors avoiding duplicates over recovering a potentially lost message after a timeout. There is no promise of exactly-once delivery across network/process failures. Malformed delivery records fail closed; investigate them before restoring a known backup. A missing/stale schedule digest still counts as that day's digest.

Event delivery retains the previous ordering: send, then confirm the transition in MySQL. Notification failure does not block recording or the next domain. The pre-existing crash window between a successful Telegram send and database commit remains; event messages have no durable outbox/retry queue. Two matching observations still confirm a change; initialization remains silent.

## Verification

Local tests use fixtures, temporary private directories, in-memory status persistence and a fake HTTP boundary. They never load production credentials, send real messages or update a live database:

```sh
php tests/run.php
php tools/preview-messages.php > /tmp/lightbot-previews.html
```

Browser preview typography is approximate; Telegram controls actual rendering. Refresh/status commands do not send Telegram or modify monitoring rows:

```sh
php schedule.php refresh
php schedule.php status
```

The explicitly requested release verification is **one combined message per group**, with labelled ON/OFF examples and today's freshly retrieved real DTEK plan. It starts with the owner's required greeting. It never changes measured state. Require a fresh, fully published day before sending:

```sh
php tools/verify-telegram.php --send --id=dtek-rich-20261110
```

Reuse the same ID on restart. Existing intent/receipt suppresses another send even if delivery was ambiguous. No cron entry should run this tool. Without `--send`, it prints message previews after refreshing the cache. A successful receipt includes the API method, message ID, group and source timestamps; success through `sendMessage` is a fallback, not proof of native rich delivery.

## Release and rollback

Before deployment, back up private `config.php` and Hestia cron metadata with restrictive permissions outside the webroot. Record the old commit. Verify remote app owner/root, master branch, clean tree and expected baseline. Publish the tested commit to origin/master and fast-forward production to the pinned SHA as hobotix. Preserve vendor, config and runtime data. No schema import, recursive webroot sync or generic service restart.

Verify deployed SHA, tracked-file hashes, PHP syntax/autoload, database connectivity in a read-only transaction, Hestia metadata/effective cron and fresh schedule cache. Do not run `run.php` as a health check. Send the explicitly authorized combined verification only after those checks.

Rollback is a reviewed Git revert deployed by pinned fast-forward. Disable/remove only the new jobs through Hestia native commands if rolling back schedule support; restore the backed-up config as needed and preserve delivery records. Do not reset/clean production or rebuild cron from a hand-written crontab. Backups include unrelated cron definitions only for recovery; never restore over newer unrelated changes without review.
