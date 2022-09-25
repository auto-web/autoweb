<?php
require_once 'vendor/autoload.php';

require_once __DIR__ . '/lib/Config.class.php';

$loader = new \Twig\Loader\FilesystemLoader('templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('public/faq.twig', ['domain_name' => Config::getValue('domain_name')]);
