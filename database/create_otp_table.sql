-- ============================================================
-- OTP Verifications Table for Forgot Password
-- Run this in phpMyAdmin on your cricket_ticket database
-- ============================================================

CREATE TABLE IF NOT EXISTS `otp_verifications` (
  `id`         int(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    int(11)      NOT NULL,
  `email`      varchar(100) NOT NULL,
  `otp_code`   varchar(6)   NOT NULL,
  `expires_at` datetime     NOT NULL,
  `is_used`    tinyint(1)   NOT NULL DEFAULT 0,
  `created_at` timestamp    NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `email`   (`email`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `otp_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
