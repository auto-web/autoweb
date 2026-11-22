<?php
require_once 'vendor/autoload.php';

require_once __DIR__ . '/lib/Config.class.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

require_once 'lib/User.class.php';

list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));

$admin = User::getUsers(['unix_username' => $_SERVER['PHP_AUTH_USER'], 'unix_password' => $_SERVER['PHP_AUTH_PW'], 'is_admin' => true]);

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

// Accounts written in the database with their creation job, in one transaction: if one of them is wrong, nothing
// is created. They stay pending until the robot has created them on the system ("Réparer" creates them again).
// $rows: [label => ['first_name' => ..., 'last_name' => ..., 'email' => ..., 'description' => ...]]
// Returns the errors, one per wrong account.
function create_accounts($rows) {
    $errors = [];
    $jobs_data = [];
    $pdo = connect();
    $pdo->beginTransaction();

    foreach ($rows as $label => $row) {
        $user = new User;
        $user->first_name = trim($row['first_name'] ?? '');
        $user->last_name = trim($row['last_name'] ?? '');
        $user->email = trim($row['email'] ?? '');
        $user->description = trim($row['description'] ?? '');
        $user->quota = new Quota();
        $user->quota->quota_limit = Config::getValue('quotas_default');

        if ($user->first_name === '' || $user->last_name === '') {
            $errors[] = $label . ' : prénom ou nom manquant.';
            continue;
        }
        if (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = $label . ' : e-mail invalide « ' . $user->email . ' ».';
            continue;
        }
        // Same connection: sees the accounts written before in this transaction, so no login nor e-mail twice
        try {
            $user->generate_user_info();
        } catch (Exception $e) {
            $errors[] = $label . ' : ' . $e->getMessage();
            continue;
        }
        if (!User::insertUser($user)) {
            $errors[] = $label . ' : enregistrement impossible (login ou e-mail déjà utilisé).';
            continue;
        }
        $jobs_data[] = ['user_id' => $user->id];
    }

    if (!$errors && !$jobs_data) {
        $errors[] = 'Aucun compte à créer.';
    }
    // Only the ids: the robot reads the accounts in the database
    if (!$errors && !Job::addJob('create_users', json_encode($jobs_data))) {
        $errors[] = 'Le lancement de la tâche de création a rencontré un problème.';
    }

    if ($errors) {
        $pdo->rollBack();
    } else {
        $pdo->commit();
    }
    return $errors;
}

// Messages of create_accounts(): the creation is refused as a whole, with the wrong accounts
function accounts_messages($errors, $success) {
    if (!$errors) {
        return [['type' => 'success', 'message' => $success]];
    }
    $messages = [['type' => 'danger', 'message' => 'Création refusée, aucun compte n\'a été créé. Corrigez puis recommencez :']];
    foreach ($errors as $error) {
        $messages[] = ['type' => 'danger', 'message' => $error];
    }
    return $messages;
}

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

    if (empty($header_indexes)) {
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

    // Accounts by line number of the file (the header is line 1)
    $rows = [];
    $line = 1;
    while (($fields = fgetcsv($handle, 1024)) !== false) {
        $line++;
        // Empty line
        if ($fields === [null]) {
            continue;
        }
        $row = [];
        foreach ($required_headers as $header) {
            $row[$header] = $fields[$header_indexes[$header]] ?? '';
        }
        $rows['Ligne ' . $line] = $row;
    }

    $messages = accounts_messages(create_accounts($rows),
        sprintf('La tâche de création de %d compte(s) a été lancée...', count($rows)));
}

if (isset($_POST['first_name'])) {
    $messages = accounts_messages(create_accounts(['Compte' => $_POST]),
        'La tâche de création de l\'utilisateur a été lancée...');
}

echo $twig->render('admin/newuser.twig', ['domain_name' => Config::getValue('domain_name'), 'messages' => $messages, 'is_admin' => $is_admin]);
