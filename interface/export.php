<?php
require_once 'vendor/autoload.php';

require_once __DIR__ . '/lib/Config.class.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

require_once 'lib/User.class.php';

list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':' , base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));

$admin = User::getUsers(["unix_username" => $_SERVER['PHP_AUTH_USER'], "unix_password" => $_SERVER['PHP_AUTH_PW'], "is_admin" => true]);

if (count($admin) == 1) {
	$_SESSION['admin'] = $admin[0];
    $is_admin = true;
} elseif ($_SESSION['admin']) {
	;
} else {
	header('WWW-Authenticate: Basic realm="AutoWeb"');
	header('HTTP/1.0 401 Unauthorized');
	echo 'Access denied';
	exit();
}

$active_users = User::getUsers(["is_active" => true]);
$filename = 'active_users_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$fp = fopen('php://output', 'w');

// Optionnel : BOM UTF-8 pour Excel
fwrite($fp, "\xEF\xBB\xBF");

if (!empty($active_users)) {
    $headers = array_keys(get_object_vars($active_users[0]));
    fputcsv($fp, $headers, ';');

    foreach ($active_users as $user) {
        if (!$user->is_admin) {
            fputcsv($fp, get_object_vars($user), ';');
        }
    }
}

fclose($fp);

