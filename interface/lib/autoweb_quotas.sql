CREATE TABLE `autoweb_quotas` (
  `user_id` VARCHAR(32) NOT NULL,
  `quota_limit` INT UNSIGNED NULL,
  `quota_used` INT UNSIGNED NULL,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_autoweb_quotas_user_id`
    FOREIGN KEY (`user_id`)
    REFERENCES `autoweb_users` (`id`)
    ON DELETE CASCADE
);

