- apt install git

- apt install python3-pip
- pip3 install ansible pymysql

- apt install mariadb-server mariadb-client

- mysql_secure_installation

- mysql
    - > CREATE USER ansible@localhost IDENTIFIED BY '....';
    - > GRANT ALL PRIVILEGES ON *.* TO 'ansible'@'localhost' WITH GRANT OPTION;

    -> create autoweb user (Users Jobs) + admin ----> (install.yml)|


QUOTA

quotaon -a

- git clone https://lab.pingveno.net/stages-2021/serveur-linux/autoweb-system.git /opt/autoweb
- git clone https://lab.pingveno.net/stages-2021/serveur-linux/autoweb-interfaces.git /usr/local/share/autoweb_webadmin

- copy /opt/autoweb/data-exemple -> /opt/autoweb/data and edit config.json

ansible-playbook /opt/autoweb/install.yml

ansible-playbook /opt/autoweb/create_user.yml


