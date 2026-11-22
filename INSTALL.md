# Installing AutoWeb

The server and the tests run on **Debian 13 (trixie)**.
All the commands are run as `root`.

The apt repositories (PHP from [Sury](https://packages.sury.org/php/)) follow the installed Debian release: Ansible reads it from `ansible_facts.distribution_release`.

## 1. Base packages

```bash
apt install git python3-venv mariadb-server mariadb-client quota
```

## 2. Ansible

Ansible is installed in the virtual environment `/opt/ansible`, the path used by the robot (`interface/robot/robot.php`). On trixie, `pip` is blocked outside of a virtual environment (PEP 668). Version 15 or later is needed for the `ansible.mysql` collection, which is not in the `ansible` package of Debian.

```bash
python3 -m venv /opt/ansible
/opt/ansible/bin/pip install 'ansible>=15' PyMySQL
```

## 3. MariaDB

```bash
mariadb-secure-installation
mariadb
```

Then, in the `mariadb` client, create the Ansible user. It must match `ansible_mysql_login` and `ansible_mysql_password` in the configuration (step 6).

```sql
CREATE USER 'ansible'@'localhost' IDENTIFIED BY '....';
GRANT ALL PRIVILEGES ON *.* TO 'ansible'@'localhost' WITH GRANT OPTION;
```

## 4. Quotas

The partition set in `fs` in the configuration (for example `/data`) must be mounted with the `usrquota` option in `/etc/fstab`. Then:

```bash
quotacheck -cum /data
quotaon -a
```

Without quotas, set `quotas_enabled: false` in the configuration.

## 5. Code

```bash
git clone https://github.com/auto-web/autoweb.git /opt/autoweb
```

## 6. Configuration

```bash
cp -r /opt/autoweb/robot/data-exemple /opt/autoweb/robot/data
editor /opt/autoweb/robot/data/config.yml
```

The `robot/data/` directory is not versioned. Ansible restricts it to `root` (0750), as it contains the passwords.

The users connect with SSH/SFTP to a dedicated, chrooted sshd (service `sftp`) on `sftp_port` (22 by default). With 22, the admin SSH of the server (service `ssh`, `/etc/ssh/sshd_config`) must listen on another port **before** the installation, otherwise the installation stops: port 22 must be free. To keep the admin SSH on port 22, choose another `sftp_port` (for example 2222). This port is shown to the users in the emails and the FAQ.

The first admin of the web interface is created with `admin_login` (lowercase letters and digits, 32 characters max) and `admin_email`. Leave `admin_password` empty: this password is given at install time (step 7), so it is not stored on disk.

## 7. Installation

```bash
cd /opt/autoweb/robot
read -rs ADMIN_PASSWORD   # password of the first admin, kept out of the shell history
/opt/ansible/bin/ansible-playbook install.yml -e admin_password="$ADMIN_PASSWORD"
unset ADMIN_PASSWORD
```

The playbook installs and configures Apache, PHP 8.2 to 8.5, the jail, DNS, the Let's Encrypt certificates, phpMyAdmin, SFTP/FTP and sendmail-wrapper. For the web interface, it:

- installs the dependencies with Composer (Twig, Bootstrap, jQuery, Phinx), at the versions of `interface/composer.lock`;
- creates the `autoweb` database and its schema with the Phinx migrations (see "Database");
- creates the first admin if there is none: login `admin_login`, email `admin_email`, and the password given with `-e admin_password=…`. If the password is missing, or if the login or the email is invalid, the installation stops with a message;
- installs the cron jobs of the robot (`robot.php` every minute, `quotas_update.php` every 10 minutes).

The Apache logs of each site are written in `/var/log/apache2/autoweb/<login>/`, and the user sees them read-only in `~/logs` (bind mount declared in `/etc/fstab`). The user can neither delete nor modify them, which would prevent Apache from restarting.

The admin interface is then available at `https://<domain_users>/admin.php`, with `admin_login` and this password. User accounts are created from the interface: the robot runs the `create_user.yml` playbook and the others.

## Upgrade

See [UPGRADE.md](UPGRADE.md): upgrading the server, partial runs with tags, and one-time operations to do after some upgrades.

## Database

The schema of the `autoweb` database is managed by [Phinx](https://phinx.org). The migrations are in `interface/db/migrations/`, the configuration in `interface/phinx.php`, and the `phinxlog` table keeps the list of the applied migrations.

The migrations are applied automatically on each run of `install.yml`, after a backup (see [UPGRADE.md](UPGRADE.md)).

### Adding a migration

In a development environment (see `interface/README.md`):

```bash
cd interface
vendor/bin/phinx create MyChange -c phinx.php
```

Write `up()` and `down()` in the file created in `db/migrations/`, commit, then deploy with the upgrade procedure.
