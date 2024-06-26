<?php
require_once 'vendor/autoload.php';

require_once __DIR__ . '/lib/Config.class.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

require_once 'lib/User.class.php';
require_once 'lib/Quota.class.php';

if (isset($_GET['user_id']))
    $user_id = $_GET['user_id'];

list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':' , base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));

$admin = User::getUsers(["unix_username" => $_SERVER['PHP_AUTH_USER'], "unix_password" => $_SERVER['PHP_AUTH_PW'], "is_admin" => true]);
if (count($admin) == 1) {
    $_SESSION['admin'] = $admin[0];
    $is_admin = true;
    if (!isset($_GET['user_id']))
        $user_id = $admin[0]->id;
} else {

    $is_admin = false;
    $user = User::getUsers(["unix_username" => $_SERVER['PHP_AUTH_USER'], "unix_password" => $_SERVER['PHP_AUTH_PW']]);
    if (count($user) == 1) {
        $_SESSION['user'] = $user[0];
        $user_id = $user[0]->id;
    }

}

if (@$_SESSION['admin'] || $_SESSION['user']) {
    ;
} else {
    header('WWW-Authenticate: Basic realm="AutoWeb"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Access denied';
    exit();
}

require_once 'lib/Job.class.php';

if (isset($user_id)) {
    $messages = [];

    $valid_php_versions = array('7.2', '7.3', '7.4', '8.0', '8.1', '8.2', '8.3');

    if (isset($_POST['php_version']) && in_array($_POST['php_version'], $valid_php_versions)) {
        if (Job::addJob("change_php_version", json_encode([['user_id' => $user_id, 'php_version' => $_POST['php_version']]]))) {
          $messages[] = ['type' => 'success', 'message' => 'La version de PHP est en cours de changement...'];
        } else {
          $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
        }
    }

    if ($is_admin && isset($_POST['quota']) && is_numeric($_POST['quota']) && $_POST['quota'] >= 0) {
        if (Job::addJob("change_quota", json_encode([['user_id' => $user_id, 'quota_limit' => $_POST['quota']]]))) {
          $messages[] = ['type' => 'success', 'message' => 'Le quota est en cours de changement...'];
        } else {
          $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
        }
    }

    if (isset($_POST['send_password'])) {
      if (Job::addJob("send_password", json_encode([['user_id' => $user_id]]))) {
          $messages[] = ['type' => 'success', 'message' => 'Le mot de passe va être envoyé...'];
      } else {
          $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
      }
    }

    if (isset($_POST['change_password'])) {
      if (Job::addJob("change_password", json_encode([['user_id' => $user_id]]))) {
          $messages[] = ['type' => 'success', 'message' => 'Le mot de passe va être changé...'];
      } else {
          $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
      }
    }

    $user = new User();
    $user->loadUserByID($user_id);
    if(!$user->is_active){
        echo "disabled user.";
        die;
    }

    echo $twig->render('admin/user.twig', ['domain_name' => Config::getValue('domain_name'), 'user' => $user, 'valid_php_versions' => $valid_php_versions, 'messages' => $messages, 'is_admin' => $is_admin]);

}
