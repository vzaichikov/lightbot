CREATE TABLE `device_status` (
  `domain` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `pending_status` tinyint(1) DEFAULT NULL,
  `last_change_at` datetime NOT NULL,
  `last_checked_at` datetime NOT NULL,
  PRIMARY KEY (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `status_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `started_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_events_domain_started_at` (`domain`,`started_at`),
  CONSTRAINT `fk_status_events_domain`
    FOREIGN KEY (`domain`)
    REFERENCES `device_status` (`domain`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
