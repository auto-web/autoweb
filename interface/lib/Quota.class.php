<?php
require_once dirname(__FILE__).'/connect.php';

require_once __DIR__ . '/Config.class.php';
require_once __DIR__ . '/Quota.class.php';

class Quota {

    public $user_id;
    public $quota_used;
    public $quota_limit;

    private function load($user_id, $quota_used, $quota_limit) {
        $this->user_id =        $user_id;
        $this->quota_used =     $quota_used;
        $this->quota_limit =    $quota_limit;
    }

    public function loadQuotaByUserID($user_id) {
        $pdo = connect();

        $sql = 'SELECT * FROM autoweb_quotas WHERE autoweb_quotas.user_id = :user_id';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('user_id', $user_id, PDO::PARAM_STR);
        
        try {
            $stmt->execute();
            if($stmt->rowCount() == 1){
                $result = $stmt->fetch(PDO::FETCH_OBJ);

                $this->load(
                        $result->user_id,
                        $result->quota_used,
                        $result->quota_limit
                    );

                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    public function storeQuota() {
        $pdo = connect();
        
        $sql = 'UPDATE autoweb_quotas SET quota_used = :quota_used, quota_limit = :quota_limit
                WHERE autoweb_quotas.user_id = :user_id';
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue('user_id',         $this->user_id,           PDO::PARAM_STR);
        $stmt->bindValue('quota_used',      $this->quota_used,        PDO::PARAM_STR);
        $stmt->bindValue('quota_limit',     $this->quota_limit,       PDO::PARAM_STR);

        try {
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function insertQuota($quota) {
        $pdo = connect();
        
        $sql = 'INSERT INTO autoweb_quotas (user_id, quota_used, quota_limit)
                    VALUES (:user_id, :quota_used, :quota_limit)';
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue('user_id',         $quota->user_id,           PDO::PARAM_STR);
        $stmt->bindValue('quota_used',      $quota->quota_used,        PDO::PARAM_STR);
        $stmt->bindValue('quota_limit',     $quota->quota_limit,       PDO::PARAM_STR);

        try {
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function loadExceededQuotas() {

        $pdo = connect();

        $sql = 'SELECT * FROM autoweb_quotas WHERE quota_used > quota_limit';
        $stmt = $pdo->prepare($sql);
        
        try {
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);
            $quotas = array();

            foreach ($results as $result) {
                $quota = new User();
                $quota->load(   $result->user_id,
                                $result->quota_used,
                                $result->quota_limit
                            );

                $quotas[] = $quota;
            }

            return $quotas;

        } catch (Exception $e) {
            return array();
        }

    }

    public static function getAllQuotas() {

        $pdo = connect();

        $sql = 'SELECT * FROM autoweb_quotas';
        $stmt = $pdo->prepare($sql);
        
        try {
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);
            $quotas = array();

            foreach ($results as $result) {
                $quota = new Quota();
                $quota->load(   $result->user_id,
                                $result->quota_used,
                                $result->quota_limit
                            );

                $quotas[] = $quota;
            }

            return $quotas;

        } catch (Exception $e) {
            return array();
        }

    }

    public static function updateQuotaUsed($quotas) {
        $pdo = connect();

        foreach ($quotas as $quota) {

            $sql = 'UPDATE autoweb_quotas SET quota_used = :quota_used
                    WHERE autoweb_quotas.user_id = :user_id';
            $stmt = $pdo->prepare($sql);

            $stmt->bindValue('user_id',         $quota->user_id,           PDO::PARAM_STR);
            $stmt->bindValue('quota_used',      $quota->quota_used,        PDO::PARAM_STR);

            try {
                $stmt->execute();
            } catch (Exception $e) {
                return false;
            }
        }
    }

}
