# Mise à jour d'AutoWeb

Le serveur et les tests tournent sous **Debian 13 (trixie)**. Pour l'installation, voir [install.md](install.md).
Toutes les commandes s'exécutent en `root`.

## Procédure

Le playbook d'installation est idempotent : pour mettre à jour, on le rejoue.

```bash
cd /opt/autoweb
git pull                                   # ou : git checkout <tag>
cd robot
/opt/ansible/bin/ansible-playbook install.yml --check --diff   # voir ce qui changerait
/opt/ansible/bin/ansible-playbook install.yml
```

Ensuite, consulter [Opérations ponctuelles](#opérations-ponctuelles) et faire celles qui ne l'ont pas encore été sur ce serveur.

Le paramètre `admin_password` n'est nécessaire que tant qu'il n'existe aucun admin.

## Passages partiels (tags)

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

Les tags des opérations ponctuelles (ci-dessous) ne s'exécutent jamais lors d'un passage normal : seulement si on les demande avec `--tags`.

## Base de données

À chaque passage d'`install.yml` (tag `interface`), Ansible vérifie s'il y a des migrations Phinx en attente (`interface/db/migrations/`). Si oui, il sauvegarde la base dans `/var/backups/autoweb/autoweb-<date>.sql`, puis applique les migrations. Sinon, il ne change rien.

Sur un serveur installé avant Phinx, le premier passage enregistre le schéma existant comme point de départ, sans le modifier, puis applique les migrations suivantes.

En cas de problème après une migration, on restaure la sauvegarde :

```bash
mariadb autoweb < /var/backups/autoweb/autoweb-<date>.sql
```

## Opérations ponctuelles

Ce sont des actions à faire une seule fois, après la mise à jour qui les introduit, sur les serveurs installés avant celle-ci. Elles sont sans effet sur une installation neuve, et peuvent être relancées sans risque.

### Logs Apache des utilisateurs hors de leur home

**Pourquoi.** Les logs de chaque site étaient écrits dans `~/logs`, un dossier que l'utilisateur pouvait modifier. Apache ouvre ces fichiers en root : si l'utilisateur supprimait ce dossier, Apache s'arrêtait au reload suivant (logrotate en fait un chaque nuit) pour tous les sites, et un lien symbolique permettait de faire écrire root n'importe où.

Les logs sont maintenant dans `/var/log/apache2/autoweb/<login>/`, et l'utilisateur les voit en lecture seule dans `~/logs` (montage bind déclaré dans `/etc/fstab`). Les comptes créés ou réparés après la mise à jour écrivent déjà leurs logs au nouvel endroit. En revanche, tant que l'opération n'est pas faite, leur `~/logs` reste l'ancien dossier, et l'utilisateur n'y voit pas les nouveaux logs.

**Commande**, après la mise à jour normale :

```bash
cd /opt/autoweb/robot
/opt/ansible/bin/ansible-playbook install.yml --tags migrate-user-logs
```

Pour chaque compte de la base, l'ancien `~/logs` est renommé en `~/logs-old`, que l'utilisateur garde avec ses anciens logs. Puis :
- comptes actifs : leur vhost est régénéré vers le nouveau chemin, et `~/logs` est monté ;
- comptes désactivés : seul `~/logs` est monté, et leur site reste désactivé.

**Vérification :**

```bash
findmnt -t none -o TARGET,SOURCE,OPTIONS | grep /logs   # un montage ro par compte
systemctl status apache2                                # active (running)
```

### Dossier `/opt/autoweb/data` vide

L'ancienne tâche de droits créait `/opt/autoweb/data`, alors que la configuration est dans `/opt/autoweb/robot/data`. S'il existe et qu'il est vide :

```bash
rmdir /opt/autoweb/data
```
