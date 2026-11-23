<?php

class Config {

    private static $config_file = __DIR__ . '/../config.php';
    private static $values = [];

    /**
     * Reads config.php into self::$values.
     *
     * @return void
     */
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
            'sftp_port'      => $sftp_port ?? 22,
            ];
        }
    }

    /**
     * Returns all the configuration values.
     *
     * @return array Values by name
     */
    public static function getValues() {
        self::init();

        return self::$values;
    }

    /**
     * Get one configuration value.
     *
     * @param string $key Name of the value (see getValues())
     * @return mixed The value, false when it is not set
     */
    public static function getValue($key) {
        self::init();

        if (isset(self::$values[$key])) {
            return self::$values[$key];
        } else {
            return false;
        }
    }

}
