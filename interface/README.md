# install:

- install composer:
    ```bash
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    php -r "if (hash_file('sha384', 'composer-setup.php') === '756890a4488ce9024fc62c56153228907f1545c228516cbf63f885e036d37e9a59d27d63f46af1d4d07ee0f76181c7d3') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
    php composer-setup.php
    php -r "unlink('composer-setup.php');"
    ```
- install dependencies:
    `$ ./composer.phar install`

- create or update the database (MariaDB `autoweb` database and user, password in `config.php`):
    `$ vendor/bin/phinx migrate -c phinx.php`

- add a database change (then write `up()` and `down()` in the new file of `db/migrations/`):
    `$ vendor/bin/phinx create MyChange -c phinx.php`

- start dev server:
    `$ php -S localhost:8080`