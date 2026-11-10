# DTEK refresh button

Follow-on to the approved rich-alert design and production deployment. User asks for a button that retrieves current DTEK source information and posts it in the originating group.

## Task 1: Add and deploy the callback flow

Specification: All DTEK event/digest messages carry an inline `🔄 Оновити графік ДТЕК` button. A click acknowledges promptly, refreshes the configured source, and posts today's rich digest in that same configured group. The message identifies the request time; source timestamps and unavailable/stale semantics remain explicit. No monitoring state changes. One refresh per group per minute; API retry-after may extend this. Callbacks for unknown chats, data or bot tokens cannot dispatch messages. Persist update consumption before side effects to suppress duplicate deliveries after restarts; interrupted requests may require another click.

Use one CLI long-poll worker per bot. Hestia's native cron starts/checks each worker every minute; a token-scoped lock prevents competing consumers. Workers recycle after an hour and restart within a minute after failure. No local schedules, public webhook or dependencies. Preflight confirmed two distinct bots and no webhooks. Preserve all existing cron jobs, configuration and runtime records.

Steps:
1. Write behavioral tests for buttons on rich/fallback messages, configured routing, fresh retrieval, cooldown, stale/failure output, callback acknowledgement, cursor replay/crash handling and private-state failures. Run tests; expected new assertions fail.
2. Implement transport, composer, callback handler/poller and CLI. Run complete suite and PHP lint; expected all pass.
3. Document Hestia commands, lifecycle, delivery limits. Obtain independent whole-branch review; fix important findings with failing regression tests.
4. Publish tested commit, deploy pinned origin/master SHA with existing operations safeguards. Back up Hestia metadata, add two native jobs, verify workers and existing monitoring.
5. Attach buttons to the existing verification messages and press once per group for end-to-end proof. Reuse existing verification posts, avoid repeating ON/OFF examples. Confirm source refresh and rich send receipts.

Review focus: callback trust boundary, cross-group/token isolation, replay after a send timeout/crash, worker overlap/recovery, unchanged morning/monitoring jobs, no secrets in logging.
