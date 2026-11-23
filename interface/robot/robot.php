<?php

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../lib/Config.class.php';
require_once __DIR__ . '/../lib/Job.class.php';
require_once __DIR__ . '/../lib/User.class.php';

$jobs = Job::getJobs(['status' => 'todo']);

foreach ($jobs as $job) {
    doJob($job);
}

/**
 * Runs a specific job. Each run marks the job running, then sets the jobs status to done or failed.
 *
 * @param Job $job Job to do
 * @return void
 */
function doJob($job) {
    switch ($job->operation) {
        case 'create_users':
            create_users($job);
            break;
        case 'create_admins':
            create_admins($job);
            break;
        case 'reactivate':
            reactivate($job);
            break;
        case 'deactivate':
            deactivate($job);
            break;
        case 'remove_users':
            remove_users($job);
            break;
        case 'repare_all':
            repare_all($job);
            break;
        case 'repare':
            repare($job);
            break;
        case 'send_password':
            send_password($job);
            break;
        case 'change_password':
            change_password($job);
            break;
        case 'change_php_version':
            change_php_version($job);
            break;
        case 'change_quota':
            change_quota($job);
            break;
    }
}

/**
 * Creates on the system the accounts written as pending by newuser.php (create_user.yml): active once it
 * succeeds. When it fails, they stay pending, and "Relancer la création" (admin.php) adds a new create_users job.
 *
 * @param Job $job Job whose data is [{"user_id": ...}, ...]; failed when an id is missing or unknown
 * @return void
 */
function create_users($job) {
    $users_data = json_decode($job->data);
    $job->markAs('running');
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        // No user_id: job of a former version, whose accounts are not in the database
        if (!isset($item->user_id) || !$user->loadUserByID($item->user_id)) {
            $job->markAs('failed');
            return;
        }
        $users[] = $user;
    }

    $full_admins = User::getUsers(['is_admin' => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ['users' => $users, 'admins' => $admins];

    file_write(json_encode($data), '/opt/autoweb/robot/data/args.json');
    $outputs = null;
    $return_var = null;
    exec('/opt/ansible/bin/ansible-playbook -i /opt/autoweb/robot/inventory/localhost.yml /opt/autoweb/robot/create_user.yml', $output, $return_var);


    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    // Already in the database (newuser.php): only the status changes
    foreach ($users as $user) {
        $user->setStatus(User::STATUS_ACTIVE);
    }

    $job->markAs('done');

}

/**
 * Creates admin accounts (create_user.yml), grants them read access to the databases of all the accounts
 * (grant_admin.yml), then inserts them in the database as active. No page adds this job today.
 *
 * @param Job $job Job whose data is the list of the admin accounts, with all their properties
 * @return void
 */
function create_admins($job) {
    $users_data = json_decode($job->data);

    $job->markAs('running');

    $full_admins = User::getUsers(['is_admin' => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ['users' => $users_data, 'admins' => $admins];

    file_write(json_encode($data), '/opt/autoweb/robot/data/args.json');
    $output = null;
    $return_var = null;
    exec('/opt/ansible/bin/ansible-playbook -i /opt/autoweb/robot/inventory/localhost.yml /opt/autoweb/robot/create_user.yml', $output, $return_var);

    echo $output;

    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    $full_users = User::getAllUsers();

    foreach ($full_users as $full_user) {
        $users[] = ['mysql_username' => $full_user->mysql_username];
    }

    $data = ['admins' => $users_data, 'users' => $users];

    file_write(json_encode($data), '/opt/autoweb/robot/data/args.json');
    exec('/opt/ansible/bin/ansible-playbook -i /opt/autoweb/robot/inventory/localhost.yml /opt/autoweb/robot/grant_admin.yml', $output, $return_var);

    echo $output;

    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    foreach ($users_data as $user) {
        $user->status = User::STATUS_ACTIVE;
        User::insertUser($user);
    }

    $job->markAs('done');

}

/**
 * Reactivates disabled accounts (create_user.yml), then saves them as active.
 *
 * @param Job $job Job whose data is [{"user_id": ...}, ...]
 * @return void
 */
function reactivate($job) {
    $users_data = json_decode($job->data);
    $job->markAs('running');
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        $user->loadUserByID($item->user_id);
        $user->status = User::STATUS_ACTIVE;
        $users[] = $user;
    }

    $full_admins = User::getUsers(['is_admin' => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ['users' => $users, 'admins' => $admins];

    file_write(json_encode($data), '/opt/autoweb/robot/data/args.json');
    $outputs = null;
    $return_var = null;
    exec('/opt/ansible/bin/ansible-playbook -i /opt/autoweb/robot/inventory/localhost.yml /opt/autoweb/robot/create_user.yml', $output, $return_var);

    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs('done');
}

/**
 * Disables accounts (disable_user.yml: no login, site and DNS disabled), then saves them as inactive.
 *
 * @param Job $job Job whose data is [{"user_id": ...}, ...]
 * @return void
 */
function deactivate($job) {
    $users_data = json_decode($job->data);
    $job->markAs('running');
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        $user->loadUserByID($item->user_id);
        $user->status = User::STATUS_INACTIVE;
        $users[] = $user;
    }

    $full_admins = User::getUsers(['is_admin' => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ['users' => $users, 'admins' => $admins];

    file_write(json_encode($data), '/opt/autoweb/robot/data/args.json');
    $outputs = null;
    $return_var = null;
    exec('/opt/ansible/bin/ansible-playbook -i /opt/autoweb/robot/inventory/localhost.yml /opt/autoweb/robot/disable_user.yml', $output, $return_var);

    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs('done');
}

/**
 * Removes disabled accounts from the system (remove_user.yml), then from the database.
 *
 * @param Job $job Job whose data is [{"user_id": ...}, ...]; failed when one of the accounts is not inactive
 * @return void
 */
function remove_users($job) {
    $users_data = json_decode($job->data);
    $job->markAs('running');
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        $user->loadUserByID($item->user_id);
        $users[] = $user;
        if ($user->status !== User::STATUS_INACTIVE) {
            $job->markAs('failed');
            return;
        }
    }

    $full_admins = User::getUsers(['is_admin' => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ['users' => $users, 'admins' => $admins];

    file_write(json_encode($data), '/opt/autoweb/robot/data/args.json');
    $outputs = null;
    $return_var = null;
    exec('/opt/ansible/bin/ansible-playbook -i /opt/autoweb/robot/inventory/localhost.yml /opt/autoweb/robot/remove_user.yml', $output, $return_var);

    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    foreach ($users as $user) {
        $user->removeUser();
    }

    $job->markAs('done');
}

/**
 * Creates again all the active accounts on the system (create_user.yml), as they are in the database.
 *
 * @param Job $job Job without data
 * @return void
 */
function repare_all($job) {
    $job->markAs('running');

    $users = User::getUsers(['status' => User::STATUS_ACTIVE]);

    $full_admins = User::getUsers(['is_admin' => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ['users' => $users, 'admins' => $admins];

    file_write(json_encode($data), '/opt/autoweb/robot/data/args.json');
    $outputs = null;
    $return_var = null;
    exec('/opt/ansible/bin/ansible-playbook -i /opt/autoweb/robot/inventory/localhost.yml /opt/autoweb/robot/create_user.yml', $output, $return_var);

    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs('done');
}

/**
 * Creates again active accounts on the system (create_user.yml), as they are in the database.
 *
 * @param Job $job Job whose data is [{"user_id": ...}, ...]; failed, before create_user.yml, when an id is
 *                 unknown or an account is not active
 * @return void
 */
function repare($job) {
    $users_data = json_decode($job->data);
    $job->markAs('running');
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        if (!$user->loadUserByID($item->user_id) || $user->status !== User::STATUS_ACTIVE) {
            $job->markAs('failed');
            return;
        }
        $users[] = $user;
    }

    $full_admins = User::getUsers(['is_admin' => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ['users' => $users, 'admins' => $admins];

    file_write(json_encode($data), '/opt/autoweb/robot/data/args.json');
    $outputs = null;
    $return_var = null;
    exec('/opt/ansible/bin/ansible-playbook -i /opt/autoweb/robot/inventory/localhost.yml /opt/autoweb/robot/create_user.yml', $output, $return_var);

    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs('done');
}

/**
 * Sends to each account its current password by e-mail (templates/mail/password.twig).
 *
 * @param Job $job Job whose data is [{"user_id": ...}, ...]; failed when one of the e-mails cannot be sent
 * @return void
 */
function send_password($job) {
    $users_data = json_decode($job->data);
    $job->markAs('running');
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        $user->loadUserByID($item->user_id);
        $users[] = $user;
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
    $twig = new \Twig\Environment($loader);

    $return_var = 0;
    foreach ($users as $user) {
        // Send password HERE
        $from = $user->unix_username . '@' . Config::getValue('domain_name');
        $to = $user->email;
        $headers = [
        'from' => $from,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=utf-8',
        ];
        $message = $twig->render('mail/password.twig', [
            'domain_name' => Config::getValue('domain_name'),
            'sftp_port' => Config::getValue('sftp_port'),
            'user' => $user,
        ]);
        $sent = mail($to, 'Votre mot de passe pour ' . $user->domain_name, $message, $headers);
        if (!$sent) {
            $return_var = 1; // An error occurred for one or more mails
        }
    }

    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    $job->markAs('done');

}

/**
 * Sets a new password to each account: sent by e-mail, applied by change_password.yml, then saved.
 *
 * @param Job $job Job whose data is [{"user_id": ...}, ...]
 * @return void
 */
function change_password($job) {
    $users_data = json_decode($job->data);
    $job->markAs('running');
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        $user->loadUserByID($item->user_id);
        $user->generate_user_password();
        $users[] = $user;
    }

    $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
    $twig = new \Twig\Environment($loader);

    foreach ($users as $user) {
        // Send password HERE
        $from = $user->unix_username . '@' . Config::getValue('domain_name');
        $to = $user->email;
        $headers = [
        'from' => $from,
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=utf-8',
        ];
        $message = $twig->render('mail/password.twig', [
            'domain_name' => Config::getValue('domain_name'),
            'sftp_port' => Config::getValue('sftp_port'),
            'user' => $user,
        ]);
        $sent = mail($to, 'Votre mot de passe pour ' . $user->domain_name, $message, $headers);
    }

    file_write(json_encode(['users' => $users]), '/opt/autoweb/robot/data/args.json');
    $outputs = null;
    $return_var = null;
    exec('/opt/ansible/bin/ansible-playbook -i /opt/autoweb/robot/inventory/localhost.yml /opt/autoweb/robot/change_password.yml', $output, $return_var);

    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs('done');

}

/**
 * Changes the PHP version of the sites (change_php_version.yml), then saves it.
 *
 * @param Job $job Job whose data is [{"user_id": ..., "php_version": "8.4"}, ...]
 * @return void
 */
function change_php_version($job) {
    $users_data = json_decode($job->data);
    $job->markAs('running');
    $users = [];

    foreach ($users_data as $item) {
        $user_id = $item->user_id;
        $ver_php = $item->php_version;

        $user = new User();
        $user->loadUserByID($user_id);
        $user->php_version = $ver_php;

        $users[] = $user;
    }

    file_write(json_encode(['users' => $users]), '/opt/autoweb/robot/data/args.json');
    $outputs = null;
    $return_var = null;
    exec('/opt/ansible/bin/ansible-playbook -i /opt/autoweb/robot/inventory/localhost.yml /opt/autoweb/robot/change_php_version.yml', $output, $return_var);

    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs('done');
}

/**
 * Changes the disk quota of accounts (change_quota.yml), then saves it.
 *
 * @param Job $job Job whose data is [{"user_id": ..., "quota_limit": <MB>}, ...]
 * @return void
 */
function change_quota($job) {
    $quota_data = json_decode($job->data);
    $job->markAs('running');
    $users = [];

    foreach ($quota_data as $item) {
        $user_id = $item->user_id;
        $quota_limit = $item->quota_limit;

        $user = new User();
        $user->loadUserByID($user_id);
        $user->quota->quota_limit = $quota_limit;

        $users[] = $user;
    }

    file_write(json_encode(['users' => $users]), '/opt/autoweb/robot/data/args.json');
    $outputs = null;
    $return_var = null;
    exec('/opt/ansible/bin/ansible-playbook -i /opt/autoweb/robot/inventory/localhost.yml /opt/autoweb/robot/change_quota.yml', $output, $return_var);

    if ($return_var != 0) {
        $job->markAs('failed');
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs('done');
}

/**
 * Writes a file, replacing its content (data/args.json of the playbooks).
 *
 * @param string $data Content
 * @param string $file Path of the file
 * @return void
 */
function file_write($data, $file) {
    $handle = fopen($file, 'w');
    fwrite($handle, $data);
    fclose($handle);
}

