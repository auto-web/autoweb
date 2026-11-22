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
#     development/incus.sh check             check the services and the admin page
#     development/incus.sh check-account     create an account with the robot, check its site and logs
#                                            (also run by .github/workflows/install.yml)
set -euo pipefail

NAME="autoweb-dev"
IMAGE="images:debian/13"
REPO="$(cd "$(dirname "$0")/.." && pwd)"
# domain_users and chroot_path of development/config.yml
DOMAIN="autoweb.localhost"
CHROOT="/data/chroot"
ADMIN_PASSWORD="admin"
# Sites of the machine, from this computer (proxy devices created by "up")
URL="https://$DOMAIN:8443"
FAILED=0

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

ok() {
    echo "ok   $*"
}

fail() {
    echo "FAIL $*" >&2
    FAILED=1
}

# HTTPS request to a site of the machine (self-signed certificate), through the proxy device:
# curl_site <host> <curl options...>
curl_site() {
    local host="$1"
    shift
    curl -sk --max-time 30 --resolve "$host:8443:127.0.0.1" "$@"
}

# expect_status <expected HTTP code> <host> <path> [curl options...]
expect_status() {
    local expected="$1" host="$2" path="$3" status
    shift 3
    status=$(curl_site "$host" -o /dev/null -w '%{http_code}' "$@" "https://$host:8443$path" || true)
    if [ "$status" = "$expected" ]; then
        ok "$path: HTTP $status"
    else
        fail "$path: HTTP $status, expected $expected"
    fi
}

# Services and admin page (after "up")
check() {
    local service units

    for service in apache2 mariadb named sftp cron; do
        if run systemctl is-active --quiet "$service"; then
            ok "service $service active"
        else
            fail "service $service not active"
        fi
    done
    units=$(run systemctl list-units --failed --plain --no-legend)
    if [ -z "$units" ]; then
        ok "no failed systemd unit"
    else
        fail "failed systemd units: $units"
    fi

    expect_status 401 "$DOMAIN" /admin.php
    expect_status 200 "$DOMAIN" /admin.php -u "admin:$ADMIN_PASSWORD"
    if curl_site "$DOMAIN" -u "admin:$ADMIN_PASSWORD" "$URL/admin.php" | grep -q 'id="users-table"'; then
        ok "/admin.php: users list shown"
    else
        fail "/admin.php: no users list"
    fi
    # Front-end libraries installed by Composer (Apache Aliases)
    expect_status 200 "$DOMAIN" /assets/bootstrap/css/bootstrap.min.css
    expect_status 200 "$DOMAIN" /assets/bootstrap/js/bootstrap.bundle.min.js
    expect_status 200 "$DOMAIN" /assets/jquery/jquery.min.js
    # Internal files never served
    expect_status 403 "$DOMAIN" /config.php
    expect_status 403 "$DOMAIN" /vendor/autoload.php
    expect_status 403 "$DOMAIN" /templates/base.twig
}

# Account created by the robot from the interface, as an admin does: job, unix user, PHP site, logs
check_account() {
    local email csv status login domain php tries=0

    email="ci.$(date +%s)@example.com"
    csv=$(mktemp)
    printf 'first_name,last_name,email,description\nCi,Test,%s,Account of incus.sh check-account\n' "$email" > "$csv"
    curl_site "$DOMAIN" -u "admin:$ADMIN_PASSWORD" -o /dev/null -F "new_users_info=@$csv;type=text/csv" "$URL/newuser.php"
    rm -f "$csv"

    # The robot (cron, every minute) runs create_user.yml
    while :; do
        status=$(run mariadb -N autoweb -e "SELECT status FROM autoweb_jobs WHERE operation = 'create_users' ORDER BY id DESC LIMIT 1")
        if [ "$status" = "done" ] || [ "$status" = "failed" ]; then
            break
        fi
        tries=$((tries + 1))
        if [ "$tries" -ge 90 ]; then
            break
        fi
        sleep 10
    done
    if [ "$status" != "done" ]; then
        fail "job create_users: ${status:-missing}"
        return
    fi
    ok "job create_users: done"

    read -r login domain php < <(run mariadb -N autoweb -e \
        "SELECT unix_username, domain_name, php_version FROM autoweb_users WHERE email = '$email'")
    if [ -z "${login:-}" ]; then
        fail "account $email not in autoweb_users"
        return
    fi
    ok "account $login ($domain, PHP $php)"

    if run id "$login" > /dev/null; then
        ok "unix user $login"
    else
        fail "no unix user $login"
    fi

    # PHP page of the user, run by the jail
    run sh -c "echo '<?php echo \"autoweb-ok \" . PHP_MAJOR_VERSION . \".\" . PHP_MINOR_VERSION;' \
        > $CHROOT/home/$login/www/index.php && chown $login:$login $CHROOT/home/$login/www/index.php"
    if curl_site "$domain" "https://$domain:8443/" | grep -q "autoweb-ok $php"; then
        ok "site https://$domain/: PHP $php"
    else
        fail "site https://$domain/: no answer of PHP $php"
    fi

    # Logs: read-only bind mount of /var/log/apache2/autoweb/<login>, written by Apache
    if run findmnt -n -o OPTIONS "$CHROOT/home/$login/logs" | grep -qE '(^|,)ro(,|$)'; then
        ok "~/logs mounted read-only"
    else
        fail "~/logs not mounted read-only"
    fi
    if run test -s "$CHROOT/home/$login/logs/access.log"; then
        ok "~/logs/access.log written"
    else
        fail "~/logs/access.log empty or missing"
    fi
    if run runuser -u "$login" -- touch "$CHROOT/home/$login/logs/test" 2> /dev/null; then
        fail "$login can write in ~/logs"
    else
        ok "$login cannot write in ~/logs"
    fi
}

case "${1:-}" in
    up)
        up
        ;;
    check)
        require_machine
        check
        exit "$FAILED"
        ;;
    check-account)
        require_machine
        check_account
        exit "$FAILED"
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
        echo "Usage: $0 up | sync [ansible-playbook options] | reset | shell | destroy | check | check-account" >&2
        exit 1
        ;;
esac
