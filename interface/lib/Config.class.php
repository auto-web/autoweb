<?php

class Config {

    private static $config_file = __DIR__ . '/../config.php';
    private static $values = [];

    private static function init() {
        if (self::$values === []) {
            require(self::$config_file);

            self::$values = [
                'domain_name'    => $domain_name,
                'dbPassword'     => $dbPassword,
		'captcha_pubkey' => $captcha_pubkey,
		'quotas_enabled' => $quotas_enabled,
		'quotas_default' => $quotas_default,
		'quotas_root'    => $quotas_root,
            ];
        }
    }

    public static function getValues() {
        self::init();

        return self::$values;
    }

    public static function getValue($key) {
        self::init();

        if (isset(self::$values[$key])) {
            return self::$values[$key];
        } else {
            return false;
        }
    }

}
