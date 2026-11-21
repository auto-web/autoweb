#!/bin/sh
# Installs the CI tools in a Debian container (debian:trixie, like the server).
# The Debian release of the PHP repository is read from /etc/os-release, as Ansible does
# with ansible_facts.distribution_release (robot/roles/install/tasks/web_install.yml).
set -eu

PHP_VERSION=8.2
COMPOSER_VERSION=2.11.2
COMPOSER_SHA256=dd158c0afd41d15fac89e1b4e47b5d42911fe316c3687cd5edac98319b25d028
PHPCS_VERSION=4.0.4
PHPCS_SHA256=4b010cd21d8bc8a17e2504792e3c77ef8259126f24caf7eafe7eddef1fa871e9
TWIG_CS_FIXER_VERSION=4.1.2
TWIG_CS_FIXER_SHA256=c99be6142686fab8ab8cf9d412c7bdd6dd4abbb177e3b32aae3916fd8aaa5ca7

. /etc/os-release

export DEBIAN_FRONTEND=noninteractive
apt-get update
apt-get install -y --no-install-recommends ca-certificates curl git unzip

# PHP of the interface, from the same repository as the server
curl -sSfL -o /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg
echo "deb https://packages.sury.org/php/ ${VERSION_CODENAME} main" > /etc/apt/sources.list.d/php.list
apt-get update
apt-get install -y --no-install-recommends "php${PHP_VERSION}-cli" "php${PHP_VERSION}-mysql" \
    "php${PHP_VERSION}-mbstring" "php${PHP_VERSION}-xml" "php${PHP_VERSION}-zip" "$@"

# download <url> <sha256> <destination>
download() {
    curl -sSfL -o "$3" "$1"
    echo "$2  $3" | sha256sum -c -
    chmod 0755 "$3"
}
download "https://getcomposer.org/download/${COMPOSER_VERSION}/composer.phar" "$COMPOSER_SHA256" /usr/local/bin/composer
download "https://github.com/PHPCSStandards/PHP_CodeSniffer/releases/download/${PHPCS_VERSION}/phpcs.phar" \
    "$PHPCS_SHA256" /usr/local/bin/phpcs
download "https://github.com/VincentLanglet/Twig-CS-Fixer/releases/download/${TWIG_CS_FIXER_VERSION}/twig-cs-fixer.phar" \
    "$TWIG_CS_FIXER_SHA256" /usr/local/bin/twig-cs-fixer

# Checkout in a container: the workspace belongs to another user
git config --global --add safe.directory "${GITHUB_WORKSPACE:-$PWD}"
