<?php

require_once dirname(__FILE__).'/../lib/Config.class.php';
require_once dirname(__FILE__).'/../lib/Quota.class.php';

$db_quotas = Quota::getAllQuotas();

$csv = shell_exec('repquota -v -O csv '. escapeshellarg(Config::getValue('quotas_root')));

$stream = fopen('php://memory', 'r+');
fwrite($stream, $csv);
rewind($stream);

// Skip header
$array = fgetcsv($stream);

$system_quotas = [];

while ($row = fgetcsv($stream)) {
    $quota = new Quota();
    $quota->user_id = $row[0];
    $quota->quota_used = ceil($row[3] / 1024);
    $system_quotas[$quota->user_id] = $quota;
}

foreach ($db_quotas as $key => $quota) {
    if ($system_quotas[$quota->user_id]) {
        $db_quotas[$key]->quota_used = $system_quotas[$quota->user_id]->quota_used;
    }
}

Quota::updateQuotaUsed($db_quotas);

