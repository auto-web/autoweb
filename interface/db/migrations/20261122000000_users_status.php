<?php

use Phinx\Migration\AbstractMigration;

/*
 * is_active becomes status: active, inactive, or pending. The interface writes the accounts in the database when
 * they are created (one transaction for a whole CSV import), before the robot creates them on the system: they
 * stay "pending" until create_user.yml succeeds, and "Réparer" creates them again.
 */
class UsersStatus extends AbstractMigration {
    public function up(): void {
        $this->execute("ALTER TABLE autoweb_users ADD status VARCHAR(16) NOT NULL DEFAULT 'active' AFTER is_active");
        $this->execute("UPDATE autoweb_users SET status = IF(is_active, 'active', 'inactive')");
        $this->execute('ALTER TABLE autoweb_users DROP is_active');
    }

    // Pending accounts become active again: the former robot inserts the accounts only after their creation
    public function down(): void {
        $this->execute('ALTER TABLE autoweb_users ADD is_active BOOLEAN AFTER status');
        $this->execute("UPDATE autoweb_users SET is_active = (status <> 'inactive')");
        $this->execute('ALTER TABLE autoweb_users DROP status');
    }
}
