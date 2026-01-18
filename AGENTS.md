# Repository Guide (LightBot)

## Purpose
This repository is a simple PHP “pinger” that checks a device’s TCP port status and notifies a Telegram chat when the status changes. It also has optional support for a second “generator” device line.

## Key Entry Points
- `run.php`: runs the main status check by instantiating `Hobotix\LightBot\LightBot` and calling `checkStatus()`.
- `shedule.php`: placeholder that calls `getShedule()` (currently empty).

## Core Logic
- `src/Hobotix/LightBot/LightBot.php`:
  - Checks device availability via `fsockopen` using values from `Config`.
  - Debounces status changes using “pending” files in a `status/` directory so that a change is only confirmed on two consecutive runs.
  - Sends Telegram messages via `sendTelegramMessage()` when a change is confirmed.
  - Tracks timestamps for ON/OFF transitions in status files.

## Configuration
- `src/Hobotix/LightBot/Config.php`:
  - Device IP/port/timeout.
  - Generator IP/port/timeout (set `gen_ip` to `false` to disable).
  - Telegram bot token and chat ID.
  - Names of status and timestamp files written to `status/`.

## Message Templates
- `src/Hobotix/LightBot/Texts.php`: message templates for main line status changes.
- `src/Hobotix/LightBot/Texts2.php`: message templates for generator status changes.

## Operational Notes
- The `status/` directory is created at runtime next to the repository root (one level above `src/`).
- Status changes are confirmed only after two consecutive runs report the same new state (debounce logic).

## Conventions
- Namespace: `Hobotix\Lightbot` (note the lowercase `b` in `Lightbot`).
- No formal testing setup present.
