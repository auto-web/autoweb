<?php
require_once 'vendor/autoload.php';

require_once __DIR__ . '/lib/Config.class.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);


require_once 'lib/User.class.php';
require_once 'lib/Job.class.php';

//TODO: recaptcha

if (isset($_POST['email'])) {
    $messages = [];
    $user = User::getUsers(["email" => $_POST['email']]);
    if (count($user) == 1) {
      if (Job::addJob("send_password", json_encode([['user_id' => $user[0]->id]]))) {
        $messages[] = ['type' => 'success', 'message' => 'Mot de passe en cours d\'envoi...'];
      } else {
        $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
      }
    }
}

echo $twig->render('admin/password.twig', ['captcha_pubkey' => Config::getValue('captcha_pubkey'), 'domain_name' => Config::getValue('domain_name'), 'messages' => $messages]);
