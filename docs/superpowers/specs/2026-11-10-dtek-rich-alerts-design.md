# DTEK schedules and Telegram rich alerts

Date: 2026-11-10. Status: proposed design, awaiting approval.

## Outcome

Both existing Telegram groups receive readable Ukrainian rich-message blocks, preserving their deliberately obscene voice. Each confirmed power transition includes the next applicable DTEK planned transition. A morning message lists today's planned outages. All recurring work runs on production; Mini Mon is read-only reference material.

## Confirmed context and decisions

- Production and local baseline: `eeba3a65ec62debe591695a4ae8838ecfc0bfa9d`; clean and aligned with origin/master at inspection.
- PHP 8.4, Composer autoload, MySQL `device_status` and `status_events`; monitoring runs every minute. Existing two-observation debounce remains.
- Both bot destinations were verified as Telegram `group` using read-only `getChat` calls.
- The user chose native rich-message blocks over rendered image cards.
- `light.hobotix.dev`: DTEK Kyiv Oblast, `kyiv-region`, group `GPV5.2`.
- `light.charmpole.dance`: DTEK Kyiv city, `kyiv`, group `GPV14.1`, using the user's explicit instruction to use Берестейський 56А. Accessible DTEK staging address lookup returned this mapping; plain 56 was absent. This is an accepted address substitution, not verification that 56 has the same electrical supply.
- Telegram's current `sendWidget` is limited to private chats. Use `sendRichMessage` with `rich_message.html` for the existing groups.

## Approaches

1. **Native rich messages — selected.** Headings, quotations, compact tables, dividers, footer and links; readable/selectable content, theme controlled by Telegram. No arbitrary CSS dashboards.
2. **Rendered image cards — not selected.** Custom styling, but image rendering adds dependencies and users cannot select the text.
3. **HTML widgets — unsuitable for the current destinations.** The API currently restricts them to private chats.

For DTEK data, port Mini Mon's normalized feed reader to PHP. Its public DTEK mirror avoids maintaining browser sessions on the shared production server. Direct DTEK scraping would require handling its currently observed anti-bot challenge. Messages identify DTEK as the plan source and the mirror as the delivery source.

## Message design

Creative direction: compact, blunt, readable. Status and the next useful time dominate. Obscenity stays in the headline/commentary; time labels and uncertainty stay clear. Keep Hobotix's generator/home voice distinct from Charmpole's studio/pole-dance voice. The attachment is neither a style reference nor a data source.

Product hierarchy for each event:

1. Explicit observed status (СВІТЛО Є / СВІТЛА НЕМАЄ), domain label, confirmation time.
2. Random rude headline and one supporting phrase.
3. Prominent DTEK planned restoration when OFF, or next planned outage when ON.
4. Duration of the state that just ended, not the new state's duration.
5. Group, plan update time, freshness/uncertainty, links to DTEK and the existing domain dashboard.

Illustrative OFF message (invented times, not a live schedule):

> **🔴 СВІТЛА НЕМАЄ · HOBOTIX**
>
> **Світло зʼїбалось.** Мережа лягла. Генератор, вилазь.
>
> **За планом ДТЕК: увімкнення о 18:30**
>
> Світло протрималось 3 год 15 хв. Смішно.
>
> Київщина · група 5.2 · план оновлено о 14:05.
> План може змінитися. Фактичний стан визначає бот.

Illustrative ON message:

> **🟢 СВІТЛО Є · CHARMPOLE**
>
> **Єбать! Світло!** Пілон видно. Можна знову красиво страждати.
>
> **За планом ДТЕК: наступне вимкнення о 21:00**
>
> Без світла 2 год 30 хв. Темрява заїбала, але ми її пережили.
>
> Київ · Берестейський 56А · група 14.1.

Morning digest: date and location; random domain-specific rude opener; compact two-column table of OFF intervals, separately labelled possible-outage intervals; total planned OFF duration; current plan metadata; links. A confirmed all-ON day explicitly says no planned outages. An unpublished, stale or invalid day never says no outages.

Use supported native tags only. Escape all external/configuration content. Retain a basic HTML `sendMessage` representation for explicit unsupported-rich-format API rejection; do not resend on ambiguous network failure because the first request may have delivered. Neither event nor digest requires interactive callbacks or a webhook.

Expand every existing phrase collection to 25 unique entries per domain (ON/OFF headline, supporting line and duration); add a small domain-specific digest opener collection. Keep status recognisable regardless of random phrase. No fabricated voltage, battery, inverter or generator telemetry.

## Schedule interpretation

Reference implementations (read-only):

- `/mnt/c/caddy/minimon-bridge/outages.mjs`
- `/mnt/c/caddy/minimon/assets/outages.js`
- `/mnt/c/caddy/minimon-bridge/outages.test.mjs`

Feeds:

- `https://raw.githubusercontent.com/Baskerville42/outage-data-ua/main/data/kyiv-region.json`
- `https://raw.githubusercontent.com/Baskerville42/outage-data-ua/main/data/kyiv.json`

Validate region, collector success, timestamp and configured group. Read published `fact.data` only; never substitute the weekly preset for a published daily plan. The preset's time-zone map validates hour meanings.

Convert states into half-hour slots: `yes` = ON/ON, `no` = OFF/OFF, `maybe` = possible/possible, `first` = OFF/ON, `second` = ON/OFF, `mfirst` = possible/ON, `msecond` = ON/possible. Reject unknown or incomplete hourly data. Traverse real instants in Europe/Kyiv so daylight-saving days are handled correctly. Merge adjacent matching slots only when there is no gap.

Use a five-minute production refresh with HTTPS verification, fixed allowlisted source URLs, bounded response size and request timeout. Atomic file caching and a refresh lock prevent partial reads and overlapping fetches. A failed fetch retains the previous payload but marks refresh failure. Data older than 20 minutes, collector errors, or timestamps more than one minute into the future are not a trustworthy forecast.

Event enrichment reads the cache only: a DTEK outage cannot block the power probe. The morning worker refreshes before composing its digest. Cache/digest state and locks live in a private app-owned directory outside the public webroot and are configurable for isolated tests.

When observed state matches the published current slot, report the next contiguous applicable transition. An OFF-to-possible boundary is not a confirmed restoration. An unpublished next day is not a midnight restoration. Display the calendar date for transitions after today. When the observation contradicts the current plan, state the mismatch and do not present a future unrelated outage's ending as the current restoration time. If appropriate, still show the next published outage with its explicit planned label.

Fallback examples:

- `ДТЕК ще не опублікував графік на сьогодні.`
- `Графік застарів — час увімкнення не підтверджено.`
- `За графіком світло вже мало бути. Час фактичного повернення невідомий.`
- `Відключення триває до кінця опублікованого графіка. Час повернення ще невідомий.`

## Scheduling and delivery

Proposed default, subject to design approval: **07:00 Europe/Kyiv daily** for both domains.

Install production-only entries for cache refresh every five minutes and a morning worker every five minutes during 07:00–09:55 Kyiv time. The worker targets one digest per domain/date and catches up within this morning window after downtime. It sends at the first invocation at/after 07:00; subsequent runs only retry eligible failures, never normal successful sends. Explicitly set and verify timezone rather than assume cron interprets CRON_TZ.

Track domain/date delivery under a lock. Persist intent before sending and success afterward. Definite API rejection can be retried according to bounded retry policy/Telegram retry_after. Timeout or interruption after dispatch is marked uncertain and not automatically resent; document that this avoids duplicates at the cost of possible lost digests. Missing or stale schedule produces one honest unavailable-data digest, not repeated messages. No automatic later-day correction digest is in scope.

Telegram transport: verified TLS, connect/total timeouts, check HTTP and Telegram `ok`, redact tokens and payloads from logs. Event notification failure must not stop status recording or the second domain. Preserve the existing event delivery ordering and document its pre-existing send/commit crash window; a durable transactional event outbox is outside this change. Add a process lock around the minute runner to prevent overlapping runs.

## Implementation shape and boundaries

Small PHP classes for schedule normalization/querying, file cache, message composition, Telegram transport and daily delivery state, plus CLI commands for refresh and morning digest. Keep configuration per domain; preserve private credentials and current IP/port values. Dry-run previews use fixture data and no Telegram transport. No new database tables or production schema import.

Update domain configuration/example with region, group, address and display label. Adapt `LightBot` to compose rich alerts after the existing debounce decision. Keep the existing public dashboard behavior. No development cron, Windows scheduled task, Mini Mon edit, new Mini App, or manual announcement to the groups.

## Verification and rollout

Use isolated test fixtures and empty credentials. Verify half-hour boundaries, DST, midnight/unpublished tomorrow, possible states, collector failure, stale/future timestamps, unexpected region/group, malformed/missing hours, observed/planned mismatch and domain isolation. Verify once-per-date morning behavior, locking, retries versus ambiguous delivery and timezone boundaries. Verify Telegram rich payload shape, HTML escaping, fallback policy, phrase uniqueness/counts, and notification failures without losing state processing. Retain debounce/silent-initialization checks.

Lint all changed PHP. Inspect generated previews of event/digest/unknown states. Review code and changes before committing. Publish only this release to origin/master, then deploy by pinned fast-forward as hobotix in the exact configured root after rechecking remote cleanliness and baseline. Back up private config and existing crontab before targeted edits; preserve the unrelated cron entry. Configure the private runtime directory, prime schedule cache without sending, and install the production jobs. Verify deployed SHA, lint, autoload, database access read-only, permissions, cron and fresh cache. Never invoke the live minute runner as a health check.

The user's request places recurring tasks on production. This design covers implementing and deploying this release after approval, without a second generic deployment confirmation. Unexpected production drift is a separate stop-and-reconcile condition.

## Remaining approval

Approve this design, including the proposed 07:00 Kyiv digest time and the accepted 56А/14.1 address substitution already directed by the user. Routine class layout, exact phrases, test harness and supported native tag choices are implementation decisions.

## Sources

- Telegram API: https://core.telegram.org/bots/api#sendrichmessage and https://core.telegram.org/bots/api#sendwidget (checked 2026-11-10).
- DTEK Kyiv city: https://www.dtek-kem.com.ua/ua/shutdowns; accessible staging lookup: https://admin-stage2.dtek-kem.com.ua/ua/shutdowns (group provenance noted above).
- Mirror and data contract: https://github.com/Baskerville42/outage-data-ua .
