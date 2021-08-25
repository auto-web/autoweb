CREATE TABLE `autoweb_jobs` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `timestamp` TIMESTAMP,
    `operation` VARCHAR(225),
    `data` BLOB,
    `status` VARCHAR(225),
    primary key (id)
);
