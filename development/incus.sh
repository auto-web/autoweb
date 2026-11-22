#!/bin/bash
# Disposable dev machine: Incus system container (Debian 13), installed with robot/install.yml
# from the local files of the repository. See DEVELOPMENT.md.
#
#     development/incus.sh up                create, install, then snapshot "installed"
#     development/incus.sh sync [options]    copy the local files again and replay install.yml
#                                            (options of ansible-playbook, e.g. --tags interface)
#     development/incus.sh reset             back to the snapshot "installed"
#     development/incus.sh shell             root shell in the machine
#     development/incus.sh destroy           delete the machine and its snapshots
set -euo pipefail

NAME="autoweb-dev"
IMAGE="images:debian/13"
REPO="$(cd "$(dirname "$0")/.." && pwd)"
# domain_users of development/config.yml
DOMAIN="autoweb.localhost"
ADMIN_PASSWORD="admin"

run() {
    incus exec "$NAME" -- "$@"
}

exists() {
    incus info "$NAME" > /dev/null 2>&1
}

require_machine() {
    if ! exists; then
        echo "No machine $NAME: run $0 up" >&2
        exit 1
    fi
}

# Boot finished and network up (apt and pip download during the installation)
wait_ready() {
    local tries=0
    run systemctl is-system-running --wait > /dev/null || true
    until run getent hosts deb.debian.org > /dev/null; do
        tries=$((tries + 1))
        if [ "$tries" -ge 60 ]; then
            echo "No network in $NAME after 60 s (no IPv4 address or no DNS)." >&2
            echo "A firewall of this computer (ufw) may block DHCP and DNS on incusbr0: see DEVELOPMENT.md." >&2
            exit 1
        fi
        sleep 1
    done
}

# Local working tree, without the files never copied (dependencies, local configuration and data).
# Files deleted locally are not deleted in the machine: use "reset" or "destroy" then "up".
push_code() {
    run mkdir -p /opt/autoweb/robot/data
    tar -C "$REPO" -czf - \
        --exclude=./.git --exclude=./.claude \
        --exclude=./interface/vendor --exclude=./interface/composer.phar --exclude=./interface/config.php \
        --exclude=./robot/data \
        . | run tar -C /opt/autoweb --no-same-owner -xzf -
    run cp /opt/autoweb/development/config.yml /opt/autoweb/robot/data/config.yml
}

# Let's Encrypt is skipped (tag tls): self-signed certificate created by "up"
install() {
    run sh -c "cd /opt/autoweb/robot && /opt/ansible/bin/ansible-playbook install.yml --skip-tags tls \
        -e admin_password=$ADMIN_PASSWORD $*"
}

up() {
    if exists; then
        echo "$NAME already exists: use sync, reset or destroy" >&2
        exit 1
    fi
    # security.nesting: systemd 257 (Debian 13) starts its services in user namespaces, refused without it
    # on hosts with AppArmor (Ubuntu: kernel.apparmor_restrict_unprivileged_userns=1): no boot, no network
    incus launch "$IMAGE" "$NAME" -c security.nesting=true
    wait_ready

    # https://autoweb.localhost:8443/ and SFTP on localhost:2222 (sftp_port of development/config.yml),
    # in IPv4 and IPv6: localhost may resolve to ::1 first
    incus config device add "$NAME" https proxy listen=tcp:127.0.0.1:8443 connect=tcp:127.0.0.1:443
    incus config device add "$NAME" https6 proxy listen=tcp:[::1]:8443 connect=tcp:127.0.0.1:443
    incus config device add "$NAME" sftp proxy listen=tcp:127.0.0.1:2222 connect=tcp:127.0.0.1:2222
    incus config device add "$NAME" sftp6 proxy listen=tcp:[::1]:2222 connect=tcp:127.0.0.1:2222

    # INSTALL.md, step 1 (and cron for the robot, openssl for the certificate)
    run apt-get update
    run env DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        ca-certificates git python3-venv mariadb-server mariadb-client quota cron openssl

    # INSTALL.md, step 2
    run python3 -m venv /opt/ansible
    run /opt/ansible/bin/pip install --no-cache-dir 'ansible>=15' PyMySQL

    # INSTALL.md, step 3: ansible_mysql_login and ansible_mysql_password of development/config.yml
    run mariadb -e "CREATE USER IF NOT EXISTS 'ansible'@'localhost' IDENTIFIED BY 'ansiblepw';
        GRANT ALL PRIVILEGES ON *.* TO 'ansible'@'localhost' WITH GRANT OPTION;"

    # INSTALL.md, steps 5 and 6
    push_code

    # Self-signed certificate at the paths used by the vhosts
    run mkdir -p "/etc/letsencrypt/live/$DOMAIN"
    run openssl req -x509 -newkey rsa:2048 -nodes -days 365 -subj "/CN=$DOMAIN" \
        -addext "subjectAltName=DNS:$DOMAIN,DNS:*.$DOMAIN" \
        -keyout "/etc/letsencrypt/live/$DOMAIN/privkey.pem" \
        -out "/etc/letsencrypt/live/$DOMAIN/fullchain.pem"

    # INSTALL.md, step 7
    install

    incus snapshot create "$NAME" installed
    echo "AutoWeb dev: https://$DOMAIN:8443/admin.php ($ADMIN_PASSWORD / $ADMIN_PASSWORD)"
}

case "${1:-}" in
    up)
        up
        ;;
    sync)
        require_machine
        shift
        push_code
        install "$@"
        ;;
    reset)
        require_machine
        incus snapshot restore "$NAME" installed
        ;;
    shell)
        require_machine
        incus exec "$NAME" -- bash -l
        ;;
    destroy)
        require_machine
        incus delete --force "$NAME"
        ;;
    *)
        echo "Usage: $0 up | sync [ansible-playbook options] | reset | shell | destroy" >&2
        exit 1
        ;;
esac
