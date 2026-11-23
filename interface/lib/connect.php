<?php
require_once dirname(__FILE__).'/../config.php';

/**
 * Connection to the autoweb database. Opened once per PHP process:
 * all the queries of a request share its transactions.
 * Stops the script when the connection fails.
 *
 * @return PDO
 */
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