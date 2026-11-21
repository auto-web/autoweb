# Installation d'AutoWeb

Le serveur et les tests tournent sous **Debian 13 (trixie)**.
Toutes les commandes s'exécutent en `root`.

Les dépôts apt (PHP de [Sury](https://packages.sury.org/php/)) suivent la version de Debian installée : Ansible la lit dans `ansible_facts.distribution_release`.

## 1. Paquets de base

```bash
apt install git python3-venv mariadb-server mariadb-client quota
```

## 2. Ansible

Il est installé dans un environnement virtuel `/opt/ansible`, chemin qu'utilise le robot (`interface/robot/robot.php`). Sous trixie, `pip` est bloqué en dehors d'un environnement virtuel (PEP 668).

```bash
python3 -m venv /opt/ansible
/opt/ansible/bin/pip install ansible PyMySQL
```

## 3. MariaDB

```bash
mariadb-secure-installation
mariadb
```

Puis, dans le client `mariadb`, créer l'utilisateur d'Ansible. Il doit correspondre à `ansible_mysql_login` et `ansible_mysql_password` de la configuration (étape 6).

```sql
CREATE USER 'ansible'@'localhost' IDENTIFIED BY '....';
GRANT ALL PRIVILEGES ON *.* TO 'ansible'@'localhost' WITH GRANT OPTION;
```

## 4. Quotas

La partition indiquée par `fs` dans la configuration (par exemple `/data`) doit être montée avec l'option `usrquota` dans `/etc/fstab`. Ensuite :

```bash
quotacheck -cum /data
quotaon -a
```

Sans quotas, mettre `quotas_enabled: false` dans la configuration.

## 5. Code

```bash
git clone https://github.com/auto-web/autoweb.git /opt/autoweb
```

## 6. Configuration

```bash
cp -r /opt/autoweb/robot/data-exemple /opt/autoweb/robot/data
editor /opt/autoweb/robot/data/config.yml
```

Le dossier `robot/data/` n'est pas versionné. Ansible le réserve à `root` (0750), car il contient les mots de passe.

Le premier admin de l'interface est créé avec `admin_login` (lettres minuscules et chiffres, 32 caractères maximum) et `admin_email`. Laisser `admin_password` vide : ce mot de passe est donné à l'installation (étape 7) et n'est donc pas stocké sur le disque.

## 7. Installation

```bash
cd /opt/autoweb/robot
read -rs ADMIN_PASSWORD   # mot de passe du premier admin, sans le laisser dans l'historique du shell
/opt/ansible/bin/ansible-playbook install.yml -e admin_password="$ADMIN_PASSWORD"
unset ADMIN_PASSWORD
```

Le playbook installe et configure Apache, PHP 8.2 à 8.5, la jail, le DNS, les certificats Let's Encrypt, phpMyAdmin, SFTP/FTP et sendmail-wrapper. Pour l'interface web, il :

- installe les dépendances avec Composer (Twig, Bootstrap, jQuery, Phinx), aux versions de `interface/composer.lock` ;
- crée la base `autoweb` et son schéma avec les migrations Phinx (voir « Base de données ») ;
- crée le premier admin s'il n'y en a aucun : login `admin_login`, e-mail `admin_email`, et le mot de passe passé en `-e admin_password=…`. Si le mot de passe manque, ou si le login ou l'e-mail sont invalides, l'installation s'arrête avec un message ;
- installe les crons du robot (`robot.php` toutes les minutes, `quotas_update.php` toutes les 10 minutes).

L'interface d'administration est ensuite accessible sur `https://<domain_users>/admin.php`, avec `admin_login` et ce mot de passe. Les comptes utilisateurs se créent depuis l'interface : le robot lance les playbooks `create_user.yml` et suivants.

## Mise à jour

Le playbook d'installation est idempotent : pour mettre à jour, on le rejoue.

```bash
cd /opt/autoweb
git pull                                   # ou : git checkout <tag>
cd robot
/opt/ansible/bin/ansible-playbook install.yml --check --diff   # voir ce qui changerait
/opt/ansible/bin/ansible-playbook install.yml
```

Le paramètre `admin_password` n'est nécessaire que tant qu'il n'existe aucun admin.

Pour ne rejouer qu'une partie, on utilise `--tags`. Par exemple, après une modification de l'interface seule :

```bash
/opt/ansible/bin/ansible-playbook install.yml --tags interface
```

| Tag | Contenu |
|---|---|
| `system` | limites RAM/CPU, logrotate |
| `jail` | jailkit (relance `jk_init` quand `jk_init.ini` change) |
| `dns` | bind9 |
| `tls` | certificats Let's Encrypt |
| `web` | Apache, PHP (dépôt Sury), vhosts |
| `phpmyadmin` | phpMyAdmin |
| `interface` | interface web : Composer, config, base de données, premier admin, crons, vhost |
| `sftp` | SFTP / pure-ftpd |
| `mail` | sendmail-wrapper |

Un tag ne gère pas les dépendances : `--tags interface` suppose par exemple que `web` (PHP 8.2) a déjà été appliqué.

## Base de données

Le schéma de la base `autoweb` est géré par [Phinx](https://phinx.org). Les migrations sont dans `interface/db/migrations/`, la configuration dans `interface/phinx.php`, et la table `phinxlog` garde la liste des migrations appliquées.

À chaque passage d'`install.yml` (tag `interface`), Ansible vérifie s'il y a des migrations en attente. Si oui, il sauvegarde la base dans `/var/backups/autoweb/autoweb-<date>.sql`, puis applique les migrations. Sinon, il ne change rien.

Sur un serveur installé avant Phinx, le premier passage enregistre le schéma existant comme point de départ, sans le modifier, puis applique les migrations suivantes.

En cas de problème après une migration, on restaure la sauvegarde :

```bash
mariadb autoweb < /var/backups/autoweb/autoweb-<date>.sql
```

### Ajouter une migration

Dans un environnement de développement, voir `interface/README.md` :

```bash
cd interface
vendor/bin/phinx create NomDuChangement -c phinx.php
```

On écrit `up()` et `down()` dans le fichier créé sous `db/migrations/`, on commite, puis on déploie avec la procédure de mise à jour.
