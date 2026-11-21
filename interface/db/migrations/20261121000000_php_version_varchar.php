<?php

use Phinx\Migration\AbstractMigration;

/*
 * php_version becomes a VARCHAR (former update-20261121.sql): the allowed PHP versions are checked by the
 * application (interface/user.php), not by the database, so a new PHP version needs no migration.
 */
class PhpVersionVarchar extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE autoweb_users MODIFY php_version VARCHAR(8) NOT NULL DEFAULT '8.2'");
    }

    public function down(): void {
        $this->execute(
            'ALTER TABLE autoweb_users MODIFY php_version '
            . "ENUM('7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3') NOT NULL"
        );
    }
}
