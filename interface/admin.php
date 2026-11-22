<?php
require_once 'vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

require_once 'lib/User.class.php';
require_once __DIR__ . '/lib/Config.class.php';

list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));

$admin = User::getUsers(['unix_username' => $_SERVER['PHP_AUTH_USER'], 'unix_password' => $_SERVER['PHP_AUTH_PW'], 'is_admin' => true]);

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

// New create_users job for pending accounts (written in the database, not created on the system yet).
// An account already in a create_users job to do or running is left out: never created twice at the same time.
// Returns the message to show.
function retry_create_users($user_ids) {
    $queued = [];
    foreach (['todo', 'running'] as $status) {
        foreach (Job::getJobs(['operation' => 'create_users', 'status' => $status]) as $job) {
            foreach (json_decode($job->data) ?? [] as $item) {
                if (isset($item->user_id)) {
                    $queued[] = $item->user_id;
                }
            }
        }
    }

    $jobs_data = [];
    foreach ($user_ids as $user_id) {
        $user = new User();
        if ($user->loadUserByID($user_id) && $user->status === User::STATUS_PENDING
            && !in_array($user_id, $queued, true)) {
            $jobs_data[] = ['user_id' => $user_id];
        }
    }

    if (!$jobs_data) {
        return ['type' => 'warning', 'message' => 'Aucune création à relancer : déjà en cours, ou compte déjà créé.'];
    }
    if (!Job::addJob('create_users', json_encode($jobs_data))) {
        return ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
    }
    return ['type' => 'success', 'message' => sprintf('Création relancée pour %d compte(s)...', count($jobs_data))];
}

$messages = [];

if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'repare_all') {
        if (Job::addJob('repare_all', '')) {
            $messages[] = ['type' => 'success', 'message' => 'Tâche de réparation lancée...'];
        } else {
            $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
        }
        //echo "repare_all";
    }

    if ($action == 'create_retry_all') {
        $pending_ids = array_map(function ($user) {
            return $user->id;
        }, User::getUsers(['status' => User::STATUS_PENDING]));
        $messages[] = retry_create_users($pending_ids);
    }

    if (isset($_POST['user_id'])) {

        if ($action == 'repare') {
            if (Job::addJob('repare', json_encode([['user_id' => $_POST['user_id']]]))) {
                $messages[] = ['type' => 'success', 'message' => 'Reconstruction en cours...'];
            } else {
                $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
            }
            //echo "repare";
            //echo "user_id: " . $_POST['user_id'];

        } elseif ($action == 'deactivate') {
            if (Job::addJob('deactivate', json_encode([['user_id' => $_POST['user_id']]]))) {
                $messages[] = ['type' => 'success', 'message' => 'Désactivation en cours...'];
            } else {
                $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
            }
            //echo "deactivate";
            //echo "user_id: " . $_POST['user_id'];

        } elseif ($action == 'reactivate') {
            if (Job::addJob('reactivate', json_encode([['user_id' => $_POST['user_id']]]))) {
                $messages[] = ['type' => 'success', 'message' => 'Réactivation en cours...'];
            } else {
                $messages[] = ['type' => 'danger', 'message' => 'L\'opération a rencontré une erreur.'];
            }
            //echo "reactivate";
            //echo "user_id: " . $_POST['user_id'];

        } elseif ($action == 'create_retry') {
            $messages[] = retry_create_users([$_POST['user_id']]);
        }

    }
}

require_once 'lib/ServerStats.class.php';

$stats = new ServerStats();
$server_stats = $stats->getAllStats();

// Active, inactive and pending accounts: filtered, sorted and displayed by js/main.js (autoweb.usersTable)
$users = User::getAllUsers();

echo $twig->render('admin/index.twig', [
    'domain_name' => Config::getValue('domain_name'),
    'server_stats' => $server_stats,
    'users' => $users,
    'messages' => $messages,
    'is_admin' => $is_admin]
);

