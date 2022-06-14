<?php
require_once dirname(__FILE__).'/connect.php';
require_once dirname(__FILE__).'/../config.php';

/*
    - create_users
    - reactivate *
    - deactivate *
    - repare_all *
    - repare *
    - change_password +
    - change_php_version +
    - change_quota +
*/

class Job {

    public $id;
    public $timestamp;
    public $operation;
    public $data;
    public $status;

    private function load($id, $timestamp, $operation, $data, $status){
        $this->id =         $id;
        $this->timestamp =  $timestamp;
        $this->operation =  $operation;
        $this->data =       $data;
        $this->status =     $status;
    }

    public function markAs($status) {
        $pdo = connect();
        
        $sql = 'UPDATE autoweb_jobs SET status = :status WHERE autoweb_jobs.id = :id';
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue('id',      $this->id,  PDO::PARAM_INT);
        $stmt->bindValue('status',  $status,    PDO::PARAM_STR);

        try {
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }

    }

    public static function addJob($op, $data){
        $pdo = connect();

        $sql = 'INSERT INTO autoweb_jobs (timestamp, operation, data, status) VALUES (NOW(), :operation, :data, "todo")';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('operation',   $op, PDO::PARAM_STR);
        $stmt->bindValue('data',        $data, PDO::PARAM_STR);

        try {
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            var_dump($e);
            return false;
        }
    }

    public static function addCreateUsersJob($users){
        $json = json_encode($users);
        return Job::addJob("create_users", $json);
    }

    public static function getJobs($filters) {

        $pdo = connect();

        $sql = 'SELECT * FROM autoweb_jobs WHERE ';
        foreach ($filters as $filter => $value) {
            if ($filter === array_key_first($filters))
                $sql .= 'autoweb_jobs.' . $filter . ' = :' .$filter;
            else
                $sql .= ' AND autoweb_jobs.' . $filter . ' = :' .$filter;
        }

        $stmt = $pdo->prepare($sql);
        foreach ($filters as $filter => $value) {
            $dataType = PDO::PARAM_STR;

            if($filter == 'id')
                $dataType = PDO::PARAM_INT;
            elseif($filter == 'data')
                return false;
            
            $stmt->bindValue($filter, $value, $dataType);
        }
        
        try {
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);
            $jobs = array();

            foreach ($results as $result) {
                $job = new Job();
                $job->load(     $result->id,
                                $result->timestamp,
                                $result->operation,
                                $result->data, 
                                $result->status
                            );

                $jobs[] = $job;
            }

            return $jobs;

        } catch (Exception $e) {
            return array();
        }

    }

}
