<?php
require_once 'vendor/autoload.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

require_once 'lib/User.class.php';

list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(':' , base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));

$admin = User::getUsers(["unix_username" => $_SERVER['PHP_AUTH_USER'], "unix_password" => $_SERVER['PHP_AUTH_PW'], "is_admin" => true]);

if (count($admin) == 1) {
	$_SESSION['admin'] = $admin[0];
} elseif ($_SESSION['admin']) {
	;
} else {
	header('WWW-Authenticate: Basic realm="AutoWeb"');
	header('HTTP/1.0 401 Unauthorized');
	echo 'Access denied';
	exit();
}

require_once 'lib/Job.class.php';

$statuses = ['running', 'todo'];

$output = [];

foreach ($statuses as $status) {
	$jobs = Job::getJobs(['status' => $status]);
	
	if ($jobs && is_array($jobs)) {
		foreach ($jobs as $job) {
			$output[] = [
				'status' => $job->status,
				'date' => $job->timestamp, // strftime('%Y-%m-%d %H:%M:%S', $job->timestamp),
				'operation' => $job->operation,
			];
		}
	}
}

header('Content-Type: application/json');
echo json_encode($output);
exit();
