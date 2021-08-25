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

require_once 'lib/Job.class.php';

$messages = [];

if (isset($_FILES['new_users_info'])) {

    $handle = fopen($_FILES['new_users_info']['tmp_name'], 'r');
    if (!$handle) {
	    echo 'Unable to open the file.\n';
    }

    // Removes header
    $header_rows = fgetcsv($handle, 1024);
    $header_indexes = array_flip($header_rows);
    $required_headers = ['first_name', 'last_name', 'email', 'description'];

    if(empty($header_indexes)) {
            echo 'Missing arguments in CSV (did you change the first row?)';
	exit();
    }

    $keys = array_keys($header_indexes);
    sort($keys);
    sort($required_headers);
    if ($required_headers !== $keys) {
            echo 'Missing or extra arguments in CSV (did you change the first row?)';
	exit();
    }

    $users = [];

    while (($rows = fgetcsv($handle, 1024)) !== false) {
        $user = new User;
        $user->first_name = $rows[$header_indexes['first_name']];
        $user->last_name = $rows[$header_indexes['last_name']];
        $user->email = $rows[$header_indexes['email']];
        $user->description = $rows[$header_indexes['description']];

        if (!$user->generate_user_info()) {
            echo 'Wrong computed UID';
	    exit();
        } else {
            $users[] = $user;
        }
    }

    if (Job::addCreateUsersJob($users)) {
        $messages[] = ['type' => 'success', 'message' => 'La tâche de création des utilisateurs a été lancée...'];
    } else {
        $messages[] = ['type' => 'danger', 'message' => 'Le lancement de la tâche de création des utilisateurs a rencontré un problème...'];
    }
}

if (isset($_POST['first_name'])) {
    $user = new User;
    $user->first_name = $_POST['first_name'];
    $user->last_name = $_POST['last_name'];
    $user->email = $_POST['email'];
    $user->description = $_POST['description'];

    if (!$user->generate_user_info()) {
        echo 'Wrong computed UID';
	exit();
    }

    if (Job::addCreateUsersJob([$user])) {
        $messages[] = ['type' => 'success', 'message' => 'La tâche de création de l\'utilisateur a été lancée...'];
    } else {
        $messages[] = ['type' => 'danger', 'message' => 'Le lancement de la tâche de création de l\'utilisateur a rencontré un problème...'];
    }
}

echo $twig->render('newuser.twig', ['domain_name' => Config::getValue('domain_name'), 'messages' => $messages, 'is_admin' => $is_admin]);
