-- The allowed PHP versions are checked by the application (interface/user.php), not by the database
ALTER TABLE autoweb_users MODIFY php_version VARCHAR(8) NOT NULL DEFAULT '8.2';
