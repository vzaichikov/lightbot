-- Apply once before deploying the three-check monitor.
-- Existing unconfirmed readings restart their streak on the next poll.
ALTER TABLE `device_status`
  ADD COLUMN `pending_count` tinyint unsigned NOT NULL DEFAULT 0 AFTER `pending_status`;
