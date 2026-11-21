<?php

use Phinx\Migration\AbstractMigration;

/*
 * Schema of the autoweb database as of 2024-05-14 (former lib/autoweb_*.sql + update-20220925.sql
 * and update-20240514.sql).
 * The existing servers already have these tables: they are only created when missing, so this migration
 * is the baseline of the production database.
 */
class InitialSchema extends AbstractMigration {
    public function up(): void {
        if (!$this->hasTable('autoweb_users')) {
            $this->execute(
                "CREATE TABLE `autoweb_users` (
                    `id` VARCHAR(32) NOT NULL,
                    `first_name` VARCHAR(255) NOT NULL,
                    `last_name` VARCHAR(255) NOT NULL,
                    `unix_username` VARCHAR(32) NOT NULL,
                    `unix_password` VARCHAR(32) NOT NULL,
                    `mysql_username` VARCHAR(32) NOT NULL,
                    `mysql_password` VARCHAR(32) NOT NULL,
                    `email` VARCHAR(255)  NOT NULL,
                    `domain_name` VARCHAR(255) NOT NULL,
                    `php_version` ENUM('7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3') NOT NULL,
                    `description` VARCHAR(225),
                    `is_admin` BOOLEAN,
                    `is_active` BOOLEAN,
                    UNIQUE (email),
                    PRIMARY KEY (id)
                )"
            );
        }

        // After autoweb_users (foreign key)
        if (!$this->hasTable('autoweb_quotas')) {
            $this->execute(
                'CREATE TABLE `autoweb_quotas` (
                    `user_id` VARCHAR(32) NOT NULL,
                    `quota_limit` INT UNSIGNED NULL,
                    `quota_used` INT UNSIGNED NULL,
                    PRIMARY KEY (`user_id`),
                    CONSTRAINT `fk_autoweb_quotas_user_id`
                        FOREIGN KEY (`user_id`)
                        REFERENCES `autoweb_users` (`id`)
                        ON DELETE CASCADE
                )'
            );
        }

        if (!$this->hasTable('autoweb_jobs')) {
            $this->execute(
                'CREATE TABLE `autoweb_jobs` (
                    `id` INT NOT NULL AUTO_INCREMENT,
                    `timestamp` TIMESTAMP,
                    `operation` VARCHAR(225),
                    `data` BLOB,
                    `status` VARCHAR(225),
                    primary key (id)
                )'
            );
        }
    }

    public function down(): void {
        $this->execute('DROP TABLE IF EXISTS `autoweb_jobs`, `autoweb_quotas`, `autoweb_users`');
    }
}
