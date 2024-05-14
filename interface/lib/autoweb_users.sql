CREATE TABLE `autoweb_users` (
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
);

INSERT INTO autoweb_users (id,
                    first_name,
                    last_name,
                    unix_username,
                    unix_password,
                    mysql_username,
                    mysql_password,
                    email,
                    domain_name,
                    php_version,
                    description,
                    is_admin,
                    is_active)
VALUES (
        "admin",
        "admin",
        "admin",
        "admin",
        "admin",
        "admin",
        "admin",
        "admin@example.com",
        "admin.example.com",
        "7.2",
        "admin",
        true,
        true);
