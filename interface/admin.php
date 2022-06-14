<?php
require_once 'vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

require_once 'lib/User.class.php';
require_once __DIR__ . '/lib/Config.class.php';

list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':' , base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));

$admin = User::getUsers(["unix_username" => $_SERVER['PHP_AUTH_USER'], "unix_password" => $_SERVER['PHP_AUTH_PW'], "is_admin" => true]);

if (count($admin) == 1) {
    $_SESSION['admin'] = $admin[0];
    $is_admin = true;
} elseif (@$_SESSION['admin']) {
    ;
} else {
    header('WWW-Authenticate: Basic realm="AutoWeb"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Access denied';
    exit();
}

require_once 'lib/Job.class.php';

$messages = [];

if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == "repare_all") {
        if (Job::addJob("repare_all", "")) {
            $messages[] = ['type' => 'success', 'message' => 'Tâche de réparation lancée...'];
        } else {
            $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
        }
        //echo "repare_all";
    }
    
    if (isset($_POST['user_id'])) {

        if ($action == "repare") {
            if (Job::addJob("repare", json_encode([['user_id' => $_POST['user_id']]]))) {
                $messages[] = ['type' => 'success', 'message' => 'Reconstruction en cours...'];
            } else {
                $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
            }
            //echo "repare";
            //echo "user_id: " . $_POST['user_id'];

        } elseif ($action == "deactivate") {
            if (Job::addJob("deactivate", json_encode([['user_id' => $_POST['user_id']]]))) {
                $messages[] = ['type' => 'success', 'message' => 'Désactivation en cours...'];
            } else {
                $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
            }
            //echo "deactivate";
            //echo "user_id: " . $_POST['user_id'];

        } elseif ($action == "reactivate") {
            if (Job::addJob("reactivate", json_encode([['user_id' => $_POST['user_id']]]))) {
                $messages[] = ['type' => 'success', 'message' => 'Réactivation en cours...'];
            } else {
                $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
            }
            //echo "reactivate";
            //echo "user_id: " . $_POST['user_id'];
        }

    }
}

$active_users = User::getUsers(["is_active" => true]);
$inactive_users = User::getUsers(["is_active" => false]);

echo $twig->render('admin.twig', ['domain_name' => Config::getValue('domain_name'), 'active_users' => $active_users, 'inactive_users' => $inactive_users, 'messages' => $messages, 'is_admin' => $is_admin]);
