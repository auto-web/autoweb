<?php
require_once dirname(__FILE__).'/../config.php';

function connect() {
    $dbUsername = "autoweb";
    global $dbPassword;
    $dbName = "autoweb";
    $dbHost = "localhost";

    try {
        $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8';
        $pdo = new PDO($dsn, $dbUsername, $dbPassword);
    } catch (PDOException $e) {
        die('Error : ' . $e->getMessage());
    }
    return $pdo;
}