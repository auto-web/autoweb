<?php

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/../lib/Config.class.php';
require_once __DIR__ . '/../lib/Job.class.php';
require_once __DIR__ . '/../lib/User.class.php';

$jobs = Job::getJobs(["status" => "todo"]);

foreach($jobs as $job)
    doJob($job);

function doJob($job){
    switch ($job->operation) {
        case "create_users":
            create_users($job);
            break;
        case "create_admins":
            create_admins($job);
            break;
        case "reactivate":
            reactivate($job);
            break;
        case "deactivate":
            deactivate($job);
            break;
        case "remove_users":
            remove_users($job);
            break;
        case "repare_all":
            repare_all($job);
            break;
        case "repare":
            repare($job);
            break;
        case "send_password":
            send_password($job);
            break;
        case "change_password":
            change_password($job);
            break;
        case "change_php_version":
            change_php_version($job);
            break;
        case "change_quota":
            change_quota($job);
            break;
    }
}

function create_users($job){
    $users_data = json_decode($job->data);
    $job->markAs("running");
    
    $full_admins = User::getUsers(["is_admin" => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ["users" => $users_data, "admins" => $admins];
    
    file_write(json_encode($data), "/opt/autoweb/robot/data/args.json");
    $outputs = null;
    $return_var = null;
    exec("ansible-playbook /opt/autoweb/robot/create_user.yml", $output, $return_var);
    

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    foreach ($users_data as $user)
        User::insertUser($user);

    $job->markAs("done");

}

function create_admins($job){
    $users_data = json_decode($job->data);

    $job->markAs("running");

    $full_admins = User::getUsers(["is_admin" => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ["users" => $users_data, "admins" => $admins];

    file_write(json_encode($data), "/opt/autoweb/robot/data/args.json");
    $output = null;
    $return_var = null;
    exec("ansible-playbook /opt/autoweb/robot/create_user.yml", $output, $return_var);
    
    echo $output;

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    $full_users = User::getAllUsers();

    foreach ($full_users as $full_user) {
        $users[] = ['mysql_username' => $full_user->mysql_username];
    }

    $data = ["admins" => $users_data, "users" => $users];

    file_write(json_encode($data), "/opt/autoweb/robot/data/args.json");
    exec("ansible-playbook /opt/autoweb/robot/grant_admin.yml", $output, $return_var);

    echo $output;

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    foreach ($users_data as $user)
        User::insertUser($user);

    $job->markAs("done");

}

function reactivate($job){
    $users_data = json_decode($job->data);
    $job->markAs("running");
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        $user->loadUserByID($item->user_id);
        $user->is_active = true;
        $users[] = $user;
    }

    $full_admins = User::getUsers(["is_admin" => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ["users" => $users, "admins" => $admins];

    file_write(json_encode($data), "/opt/autoweb/robot/data/args.json");
    $outputs = null;
    $return_var = null;
    exec("ansible-playbook /opt/autoweb/robot/create_user.yml", $output, $return_var);

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs("done");
}

function deactivate($job){
    $users_data = json_decode($job->data);
    $job->markAs("running");
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        $user->loadUserByID($item->user_id);
        $user->is_active = false;
        $users[] = $user;
    }

    $full_admins = User::getUsers(["is_admin" => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ["users" => $users, "admins" => $admins];

    file_write(json_encode($data), "/opt/autoweb/robot/data/args.json");
    $outputs = null;
    $return_var = null;
    exec("ansible-playbook /opt/autoweb/robot/disable_user.yml", $output, $return_var);

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs("done");
}

function remove_users($job){
    $users_data = json_decode($job->data);
    $job->markAs("running");
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        $user->loadUserByID($item->user_id);
        $users[] = $user;
        if($user->is_active) {
            $job->markAs("failed");
            return;
        }
    }

    $full_admins = User::getUsers(["is_admin" => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ["users" => $users, "admins" => $admins];

    file_write(json_encode($data), "/opt/autoweb/robot/data/args.json");
    $outputs = null;
    $return_var = null;
    exec("ansible-playbook /opt/autoweb/robot/remove_user.yml", $output, $return_var);

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    foreach ($users as $user) {
        $user->removeUser();
    }

    $job->markAs("done");
}

function repare_all($job){
    $job->markAs("running");

    $users = User::getUsers(["is_active" => true]);

    $full_admins = User::getUsers(["is_admin" => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ["users" => $users, "admins" => $admins];

    file_write(json_encode($data), "/opt/autoweb/robot/data/args.json");
    $outputs = null;
    $return_var = null;
    exec("ansible-playbook /opt/autoweb/robot/create_user.yml", $output, $return_var);

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs("done");
}

function repare($job){
    $users_data = json_decode($job->data);
    $job->markAs("running");
    $users = [];

    foreach ($users_data as $item) {
        $users = array();
        $user = new User();
        $user->loadUserByID($item->user_id);
        $users[] = $user;
    }

    $full_admins = User::getUsers(["is_admin" => true]);
    foreach ($full_admins as $full_admin) {
        $admins[] = ['mysql_username' => $full_admin->mysql_username];
    }

    $data = ["users" => $users, "admins" => $admins];

    file_write(json_encode($data), "/opt/autoweb/robot/data/args.json");
    $outputs = null;
    $return_var = null;
    exec("ansible-playbook /opt/autoweb/robot/create_user.yml", $output, $return_var);

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs("done");
}

function send_password($job){
    $users_data = json_decode($job->data);
    $job->markAs("running");
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        $user->loadUserByID($item->user_id);
        $users[] = $user;
    }

    $loader = new \Twig\Loader\FilesystemLoader('templates');
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
	$message = $twig->render('mail/password.twig', ['domain_name' => Config::getValue('domain_name'), 'user' => $user]);
	$sent = mail($to, 'Votre mot de passe pour ' . $user->domain_name, $message, $headers);
	if (!$sent) {
	    $return_var = 1; // An error occured for one or more mail
	}
    }

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    $job->markAs("done");

}

function change_password($job){
    $users_data = json_decode($job->data);
    $job->markAs("running");
    $users = [];

    foreach ($users_data as $item) {
        $user = new User();
        $user->loadUserByID($item->user_id);
        $user->generate_user_password();
        $users[] = $user;
    }

    $loader = new \Twig\Loader\FilesystemLoader('templates');
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
	$message = $twig->render('mail/password.twig', ['domain_name' => Config::getValue('domain_name'), 'user' => $user]);
	$sent = mail($to, 'Votre mot de passe pour ' . $user->domain_name, $message, $headers);
    }

    file_write(json_encode(["users" => $users]), "/opt/autoweb/robot/data/args.json");
    $outputs = null;
    $return_var = null;
    exec("ansible-playbook /opt/autoweb/robot/change_password.yml", $output, $return_var);

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs("done");

}

function change_php_version($job){
    $users_data = json_decode($job->data);
    $job->markAs("running");
    $users = [];

    foreach ($users_data as $item) {
        $user_id = $item->user_id;
        $ver_php = $item->php_version;

        $user = new User();
        $user->loadUserByID($user_id);
        $user->php_version = $ver_php;

        $users[] = $user;
    }

    file_write(json_encode(["users" => $users]), "/opt/autoweb/robot/data/args.json");
    $outputs = null;
    $return_var = null;
    exec("ansible-playbook /opt/autoweb/robot/change_php_version.yml", $output, $return_var);

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs("done");
}

function change_quota($job){
    $quota_data = json_decode($job->data);
    $job->markAs("running");
    $users = [];

    foreach ($quota_data as $item) {
        $user_id = $item->user_id;
        $quota_limit = $item->quota_limit;

        $user = new User();
        $user->loadUserByID($user_id);
        $user->quota->quota_limit = $quota_limit;

        $users[] = $user;
    }

    file_write(json_encode(["users" => $users]), "/opt/autoweb/robot/data/args.json");
    $outputs = null;
    $return_var = null;
    exec("ansible-playbook /opt/autoweb/robot/change_quota.yml", $output, $return_var);

    if($return_var != 0) {
        $job->markAs("failed");
        return;
    }

    foreach ($users as $user) {
        $user->storeUser();
    }

    $job->markAs("done");
}

function file_write($data, $file) {
    $handle = fopen($file, 'w');
    fwrite($handle, $data);
    fclose($handle);
}

