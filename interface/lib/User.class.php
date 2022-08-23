<?php
require_once dirname(__FILE__).'/connect.php';

require_once __DIR__ . '/Config.class.php';
require_once __DIR__ . '/Quota.class.php';

class User {

    public $id;
    public $first_name;
    public $last_name;

    public $unix_username;
    public $unix_password;
    public $mysql_username;
    public $mysql_password;

    public $email;
    public $domain_name;
    public $php_version;
    public $description;

    public $is_admin;
    public $is_active;

    public $quota;

    private function load($id, $first_name, $last_name, $unix_username,
    $unix_password, $mysql_username, $mysql_password, $email, $domain_name, $php_version,
    $description, $is_admin, $is_active, $quota){
        $this->id =             $id;
        $this->first_name =     $first_name;
        $this->last_name =      $last_name;
        $this->unix_username =  $unix_username;
        $this->unix_password =  $unix_password;
        $this->mysql_username = $mysql_username;
        $this->mysql_password = $mysql_password;
        $this->email =          $email;
        $this->domain_name =    $domain_name;
        $this->php_version =    $php_version;
        $this->description =    $description;
        $this->is_admin =       $is_admin;
        $this->is_active =      $is_active;
        $this->quota =          $quota;
    }

    public function generate_user_password() {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = substr( str_shuffle( $chars ), 0, 16 );

        $this->unix_password = $password;
        $this->mysql_password = $password;
    }

    private function generate_uid($first_name_length = 1) {
        $uid = preg_replace('/[^a-z0-9]/', '',
        strtolower(
            substr(iconv('UTF-8', 'ASCII//TRANSLIT', $this->first_name), 0, $first_name_length) .
            iconv('UTF-8', 'ASCII//TRANSLIT', $this->last_name)
        ));
        $uid = substr($uid, 0, 32);
        return $uid;
    }

    public function generate_user_info() {
        global $domain_name;

        $uid = $this->generate_uid();

        if (!preg_match('/^[a-z0-9]+$/', $uid)) {
            throw new Exception('Generated UID looks corrupted.');
            return false;
        }

        $tries = 1;
        while (self::getUserById($uid) && $tries < 6) {
            $tries++;
            $uid = $this->generate_uid($tries);
        }
        if ($tries >= 6) {
            throw new Exception('Cannot calculate an original UID after 6 attempts.');
            return false;
        }

        if ($this->getUserByEmail($this->email)) {
                throw new Exception('The user email already exists in database.');
                return false;
        }

        $this->id = $uid;
        $this->unix_username = $uid;
        $this->mysql_username = $uid;

        $this->generate_user_password();

        $this->domain_name = $this->unix_username . "." . Config::getValue('domain_name');

        $this->is_admin = false;
        $this->is_active = true;
        $this->php_version = "7.2";

        return true;
    }

    public function loadUserByID($id) {
        $pdo = connect();

        $sql = 'SELECT * FROM autoweb_users WHERE autoweb_users.id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('id', $id, PDO::PARAM_STR);
        
        try {
            $stmt->execute();
            if($stmt->rowCount() == 1){
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                $quota = new Quota();
                $quota->loadQuotaByUserID($result->id);

                $this->load(
                        $result->id,
                        $result->first_name,
                        $result->last_name,
                        $result->unix_username,
                        $result->unix_password,
                        $result->mysql_username,
                        $result->mysql_password,
                        $result->email,
                        $result->domain_name,
                        $result->php_version,
                        $result->description,
                        $result->is_admin,
                        $result->is_active,
                        $quota
                    );

                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function loadUserByEmail($email) {
        $pdo = connect();

        $sql = 'SELECT * FROM autoweb_users WHERE autoweb_users.email = :email';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('email', $email, PDO::PARAM_STR);
        
        try {
            $stmt->execute();
            if($stmt->rowCount() == 1){
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                $quota = new Quota();
                $quota->loadQuotaByUserID($result->id);

                $this->load(
                        $result->id,
                        $result->first_name,
                        $result->last_name,
                        $result->unix_username,
                        $result->unix_password,
                        $result->mysql_username,
                        $result->mysql_password,
                        $result->email,
                        $result->domain_name,
                        $result->php_version,
                        $result->description,
                        $result->is_admin,
                        $result->is_active,
                        $quota
                    );

                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function storeUser() {

        if ( $this->quota->storeQuota() === false ) {
            return false;
        }

        $pdo = connect();
        
        $sql = 'UPDATE autoweb_users SET first_name = :first_name, last_name = :last_name, unix_username = :unix_username,
                                unix_password = :unix_password, mysql_username = :mysql_username, mysql_password = :mysql_password,
                                email = :email, domain_name = :domain_name, php_version = :php_version, description = :description,
                                is_admin = :is_admin, is_active = :is_active
                WHERE autoweb_users.id = :id';
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue('id',              $this->id,                PDO::PARAM_STR);
        $stmt->bindValue('first_name',      $this->first_name,        PDO::PARAM_STR);
        $stmt->bindValue('last_name',       $this->last_name,         PDO::PARAM_STR);
        $stmt->bindValue('unix_username',   $this->unix_username,     PDO::PARAM_STR);
        $stmt->bindValue('unix_password',   $this->unix_password,     PDO::PARAM_STR);
        $stmt->bindValue('mysql_username',  $this->mysql_username,    PDO::PARAM_STR);
        $stmt->bindValue('mysql_password',  $this->mysql_password,    PDO::PARAM_STR);
        $stmt->bindValue('email',           $this->email,             PDO::PARAM_STR);
        $stmt->bindValue('domain_name',     $this->domain_name,       PDO::PARAM_STR);
        $stmt->bindValue('php_version',     $this->php_version,       PDO::PARAM_STR);
        $stmt->bindValue('description',     $this->description,       PDO::PARAM_STR);
        $stmt->bindValue('is_admin',        $this->is_admin,          PDO::PARAM_BOOL);
        $stmt->bindValue('is_active',       $this->is_active,         PDO::PARAM_BOOL);

        try {
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }

    }

    public function removeUser() {
        $pdo = connect();
        
        $sql = 'DELETE FROM autoweb_users WHERE autoweb_users.id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('id',              $this->id,                PDO::PARAM_STR);

        try {
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }

    }


    public static function insertUser($user) {
        $pdo = connect();
        
        $sql = 'INSERT INTO autoweb_users (id, first_name, last_name, unix_username, unix_password, mysql_username, mysql_password, email,
                                    domain_name, php_version, description, is_admin, is_active)
                    VALUES (:id, :first_name, :last_name, :unix_username, :unix_password, :mysql_username, :mysql_password, :email,
                                    :domain_name, :php_version, :description, :is_admin, :is_active)';
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue('id',              $user->id,                PDO::PARAM_STR);
        $stmt->bindValue('first_name',      $user->first_name,        PDO::PARAM_STR);
        $stmt->bindValue('last_name',       $user->last_name,         PDO::PARAM_STR);
        $stmt->bindValue('unix_username',   $user->unix_username,     PDO::PARAM_STR);
        $stmt->bindValue('unix_password',   $user->unix_password,     PDO::PARAM_STR);
        $stmt->bindValue('mysql_username',  $user->mysql_username,    PDO::PARAM_STR);
        $stmt->bindValue('mysql_password',  $user->mysql_password,    PDO::PARAM_STR);
        $stmt->bindValue('email',           $user->email,             PDO::PARAM_STR);
        $stmt->bindValue('domain_name',          $user->domain_name,            PDO::PARAM_STR);
        $stmt->bindValue('php_version',     $user->php_version,       PDO::PARAM_STR);
        $stmt->bindValue('description',     $user->description,       PDO::PARAM_STR);
        $stmt->bindValue('is_admin',        $user->is_admin,          PDO::PARAM_BOOL);
        $stmt->bindValue('is_active',       $user->is_active,         PDO::PARAM_BOOL);

        try {
            $stmt->execute();
        } catch (Exception $e) {
            return false;
        }

        $quota = new Quota();
        $quota->user_id = $user->id;
        $quota->quota_used = 0;
        $quota->quota_limit = $user->quota->quota_limit;
        Quota::insertQuota($quota);

        return true;

    }

    public static function getAllUsers() {

        $pdo = connect();

        $sql = 'SELECT * FROM autoweb_users';
        $stmt = $pdo->prepare($sql);
        
        try {
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);
            $users = array();

            foreach ($results as $result) {
                $user = new User();
                $quota = new Quota();
                $quota->loadQuotaByUserID($result->id);
                $user->load(    $result->id,
                                $result->first_name,
                                $result->last_name,
                                $result->unix_username,
                                $result->unix_password,
                                $result->mysql_username,
                                $result->mysql_password,
                                $result->email,
                                $result->domain_name,
                                $result->php_version,
                                $result->description,
                                $result->is_admin,
                                $result->is_active,
                                $quota
                            );

                $users[] = $user;
            }

            return $users;

        } catch (Exception $e) {
            return array();
        }

    }

    public static function getUserByEmail($email) {
        return self::getUsers(['email' => $email]);
    }
    public static function getUserById($id) {

        return self::getUsers(['id' => $id]);
    }

    public static function getUsers($filters) {

        $pdo = connect();

        $sql = 'SELECT * FROM autoweb_users WHERE ';
        foreach ($filters as $filter => $value) {
            if ($filter === array_key_first($filters))
                $sql .= 'autoweb_users.' . $filter . ' = :' .$filter;
            else
                $sql .= ' AND autoweb_users.' . $filter . ' = :' .$filter;
        }

        $stmt = $pdo->prepare($sql);
        foreach ($filters as $filter => $value) {
            $dataType = PDO::PARAM_STR;

            if($filter == 'is_active' || $filter == 'is_admin')
                $dataType = PDO::PARAM_BOOL;
            
            $stmt->bindValue($filter, $value, $dataType);
        }
        
        try {
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);
            $users = array();

            foreach ($results as $result) {
                $user = new User();
                $quota = new Quota();
                $quota->loadQuotaByUserID($result->id);
                $user->load(    $result->id,
                                $result->first_name,
                                $result->last_name,
                                $result->unix_username,
                                $result->unix_password,
                                $result->mysql_username,
                                $result->mysql_password,
                                $result->email,
                                $result->domain_name,
                                $result->php_version,
                                $result->description,
                                $result->is_admin,
                                $result->is_active,
                                $quota
                            );

                $users[] = $user;
            }

            return $users;

        } catch (Exception $e) {
            return array();
        }

    }
}
