# Upgrading AutoWeb

The server and the tests run on **Debian 13 (trixie)**. For the installation, see [INSTALL.md](INSTALL.md).
All the commands are run as `root`.

## Procedure

The install playbook is idempotent: to upgrade, run it again.

```bash
cd /opt/autoweb
git pull                                   # or: git checkout <tag>
cd robot
/opt/ansible/bin/ansible-playbook install.yml --check --diff   # see what would change
/opt/ansible/bin/ansible-playbook install.yml
```

Then, read [One-time operations](#one-time-operations) and do the ones not yet done on this server.

The `admin_password` parameter is only needed as long as there is no admin.

## Partial runs (tags)

To run only a part of the playbook, use `--tags`. For example, after a change of the web interface only:

```bash
/opt/ansible/bin/ansible-playbook install.yml --tags interface
```

| Tag | Content |
|---|---|
| `system` | RAM/CPU limits, logrotate |
| `jail` | jailkit (runs `jk_init` again when `jk_init.ini` changes) |
| `dns` | bind9 |
| `tls` | Let's Encrypt certificates |
| `web` | Apache, PHP (Sury repository), vhosts |
| `phpmyadmin` | phpMyAdmin |
| `interface` | web interface: Composer, config, database, first admin, cron jobs, vhost |
| `sftp` | SFTP / pure-ftpd |
| `mail` | sendmail-wrapper |

A tag does not handle dependencies: for example, `--tags interface` assumes that `web` (PHP 8.2) has already been applied.

The tags of the one-time operations (below) never run during a normal run: only when they are given with `--tags`.

## Database

On each run of `install.yml` (tag `interface`), Ansible checks for pending Phinx migrations (`interface/db/migrations/`). If there are some, it backs up the database to `/var/backups/autoweb/autoweb-<date>.sql`, then applies the migrations. Otherwise, it changes nothing.

On a server installed before Phinx, the first run records the existing schema as the starting point, without changing it, then applies the next migrations.

If something goes wrong after a migration, restore the backup:

```bash
mariadb autoweb < /var/backups/autoweb/autoweb-<date>.sql
```

## One-time operations

These actions are done only once, after the upgrade that introduces them, on the servers installed before it. They have no effect on a new installation, and can safely be run again.

### Apache logs of the users outside of their home

**Why.** The logs of each site were written in `~/logs`, a directory the user could modify. Apache opens these files as root: if the user deleted this directory, Apache stopped on the next reload (logrotate does one every night) for every site, and a symlink let the user make root write anywhere.

The logs are now in `/var/log/apache2/autoweb/<login>/`, and the user sees them read-only in `~/logs` (bind mount declared in `/etc/fstab`). The accounts created or repaired after the upgrade already write their logs to the new place. However, until this operation is done, their `~/logs` is still the old directory, and the user does not see the new logs there.

**Command**, after the normal upgrade:

```bash
cd /opt/autoweb/robot
/opt/ansible/bin/ansible-playbook install.yml --tags migrate-user-logs
```

For each account of the database, the old `~/logs` is renamed to `~/logs-old`, which the user keeps with the old logs. Then:
- active accounts: their vhost is generated again with the new path, and `~/logs` is mounted;
- disabled accounts: only `~/logs` is mounted, and their site stays disabled.

**Check:**

```bash
findmnt -l -o TARGET,SOURCE,OPTIONS | grep '/logs ' # one ro mount per account
systemctl status apache2                           # active (running)
```

### Ansible 15 or later (`ansible.mysql` collection)

The playbooks use the `ansible.mysql` modules, which replace the deprecated `community.mysql` ones. They are included in Ansible 15 or later. Before running `install.yml`:

```bash
/opt/ansible/bin/pip install -U 'ansible>=15' PyMySQL
/opt/ansible/bin/ansible-galaxy collection list ansible.mysql   # must list the collection
```

### SSH/SFTP port of the users (`sftp_port`)

The port of the users sshd (service `sftp`) is now set by `sftp_port` in `robot/data/config.yml`. Without this key, port 22 is kept, as before: nothing to do. To use another port, add it (see `robot/data-exemple/config.yml`), run `install.yml`, and open the port in the firewall. The installation stops if the port is already used by another service.

### Empty `/opt/autoweb/data` directory

The former permissions task created `/opt/autoweb/data`, whereas the configuration is in `/opt/autoweb/robot/data`. If it exists and is empty:

```bash
rmdir /opt/autoweb/data
```
