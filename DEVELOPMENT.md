# Development

## Disposable dev machine (Incus)

`development/incus.sh` creates a Debian 13 (trixie) machine with [Incus](https://linuxcontainers.org/incus/), installed like a server ([INSTALL.md](INSTALL.md)) from the local files of the repository, with the configuration `development/config.yml`. The web interface, the robot (cron), Apache, PHP 8.2 to 8.5, the jail, DNS, phpMyAdmin, SFTP and sendmail-wrapper are the real ones, so accounts created in the interface are really created by the robot.

It is an unprivileged system container: systemd and the bind mounts of the users logs work in it, but root in the machine is not root on your computer. It is created with `security.nesting=true`, as systemd 257 (Debian 13) starts its services in user namespaces, which AppArmor refuses otherwise on Ubuntu hosts.

Differences with a server:

- Let's Encrypt is skipped (`--skip-tags tls`): the vhosts use a self-signed certificate for `autoweb.localhost` and `*.autoweb.localhost`;
- quotas are disabled (no partition with `usrquota`);
- SFTP listens on port 2222.

### Requirements

Incus, for example on Debian 13 or Ubuntu 24.04 and later:

```bash
sudo apt install incus
sudo incus admin init --minimal
sudo usermod -aG incus-admin "$USER"   # then log out and in again
```

With ufw (enabled by default on Ubuntu desktops), allow the Incus bridge, otherwise the machine gets no IPv4 address and no DNS:

```bash
sudo ufw allow in on incusbr0
sudo ufw route allow in on incusbr0
sudo ufw route allow out on incusbr0
```

### Create the machine

```bash
development/incus.sh up
```

It creates the container `autoweb-dev`, installs the base packages and Ansible, copies the local files to `/opt/autoweb` (without `interface/vendor/`, `interface/config.php` and `robot/data/`), runs `install.yml` (several minutes: packages, PHP versions, jail), then saves the snapshot `installed`.

Then open https://autoweb.localhost:8443/admin.php, accept the self-signed certificate, and log in with `admin` / `admin`. The sites of the accounts are at `https://<login>.autoweb.localhost:8443/`, and SFTP at `sftp -P 2222 <login>@localhost`.

### Use

| Command | Effect |
|---|---|
| `development/incus.sh sync` | copies the local files again and replays `install.yml`, as an upgrade ([UPGRADE.md](UPGRADE.md)) |
| `development/incus.sh sync --tags interface` | same, with options of `ansible-playbook` (here, the web interface only) |
| `development/incus.sh reset` | back to the snapshot `installed`: fresh installation, no accounts |
| `development/incus.sh shell` | root shell in the machine |
| `development/incus.sh destroy` | deletes the machine and its snapshots |
| `development/incus.sh check` | checks the services (Apache, MariaDB, DNS, SFTP, cron, no failed unit) and the admin page (authentication, assets, internal files not served) |
| `development/incus.sh check-account` | creates an account from the interface as an admin does, waits for the robot, then checks the unix user, its PHP site and its read-only logs |

`sync` does not delete in the machine the files deleted locally: use `reset`, or `destroy` then `up`.

Robot logs, after an action in the interface: `incus exec autoweb-dev -- journalctl -u cron`.

### Installation test in the CI

The GitHub Actions workflow `install` ([.github/workflows/install.yml](.github/workflows/install.yml)) runs `up`, `check` and `check-account` on a runner. It takes about 15 minutes, so it only runs when started by hand: in the Actions tab of GitHub (install > Run workflow, choose the branch), or with `gh workflow run install.yml --ref <branch>`.

### Quotas

To test the quotas, the same machine can be a virtual machine with a dedicated disk: `incus launch images:debian/13 autoweb-dev --vm` (not done by the script).
