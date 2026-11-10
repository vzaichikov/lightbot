# DTEK command menu

Follow-on to the deployed button: add `/dtek` and `/dtek@this_bot` in each configured group's Telegram command menu. Reuse the same source refresh, rich digest, routing, cooldown and durable update cursor. Other chats, other bot mentions, non-command text, bot messages and edited messages must not trigger sends. During cooldown commands remain quiet to avoid extra group spam; the existing inline button displays remaining time.

Implement in the current checkout on a feature branch with test-first changes. Subscribe polling to messages as well as callbacks; obtain bot username from getMe at worker startup, fail closed on invalid identity. Add an explicit CLI menu registration operation that preserves existing chat commands and verifies API success. Register only configured group scopes, preserving global/private menus. No new cron jobs or config secrets.

Validation: command parser/routing, targeted mentions, shared button/command cooldown, update replay, identity failure, registration preserve/failure cases, full suite and lint. One independent review before pinned deployment. Restart only the two app-owner callback workers so Hestia relaunches updated code. Verify registered menus, active workers and production alignment. Existing deployment authorization covers this follow-on.
