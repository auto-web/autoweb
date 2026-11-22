<?php
require_once dirname(__FILE__).'/../config.php';

// One connection per PHP process: all the queries of a request share its transactions (newuser.php)
function connect() {
    static $pdo = null;
    $dbUsername = 'autoweb';
    global $dbPassword;
    $dbName = 'autoweb';
    $dbHost = 'localhost';

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8';
        $pdo = new PDO($dsn, $dbUsername, $dbPassword);
    } catch (PDOException $e) {
        die('Error : ' . $e->getMessage());
    }
    return $pdo;
}