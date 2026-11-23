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

    /**
     * Sets the properties from a row of autoweb_jobs.
     *
     * @param int    $id        Id of the job
     * @param string $timestamp Date and time of its creation
     * @param string $operation Operation to be run by robot/robot.php (create_users, repare...)
     * @param string $data      Arguments of the operation, in JSON (usually [{"user_id": ...}])
     * @param string $status    todo, running, done or failed
     * @return void
     */
    private function load($id, $timestamp, $operation, $data, $status) {
        $this->id =         $id;
        $this->timestamp =  $timestamp;
        $this->operation =  $operation;
        $this->data =       $data;
        $this->status =     $status;
    }

    /**
     * Saves a new status for a job.
     *
     * @param string $status running, done or failed
     * @return bool false on a database error
     */
    public function markAs($status) {
        $pdo = connect();

        $sql = 'UPDATE autoweb_jobs SET status = :status WHERE autoweb_jobs.id = :id';
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue('id', $this->id, PDO::PARAM_INT);
        $stmt->bindValue('status', $status, PDO::PARAM_STR);

        try {
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            return false;
        }

    }

    /**
     * Adds a job to do, to be run by the robot (cron, every minute).
     *
     * @param string $op   Operation (see doJob() in robot/robot.php)
     * @param string $data Arguments of the operation, in JSON (usually [{"user_id": ...}], '' for repare_all)
     * @return bool false on a database error
     */
    public static function addJob($op, $data) {
        $pdo = connect();

        $sql = 'INSERT INTO autoweb_jobs (timestamp, operation, data, status) VALUES (NOW(), :operation, :data, "todo")';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('operation', $op, PDO::PARAM_STR);
        $stmt->bindValue('data', $data, PDO::PARAM_STR);

        try {
            $stmt->execute();
            return true;
        } catch (Exception $e) {
            var_dump($e);
            return false;
        }
    }

    /**
     * Return all jobs matching the corresponding filters.
     *
     * @param array $filters Values by column of autoweb_jobs (e.g. ['status' => 'todo']), all equal;
     *               data cannot be a filter
     * @return Job[]|false Empty on a database error, false with a data filter
     */
    public static function getJobs($filters) {

        $pdo = connect();

        $sql = 'SELECT * FROM autoweb_jobs WHERE ';
        foreach ($filters as $filter => $value) {
            if ($filter === array_key_first($filters)) {
                $sql .= 'autoweb_jobs.' . $filter . ' = :' .$filter;
            } else {
                $sql .= ' AND autoweb_jobs.' . $filter . ' = :' .$filter;
            }
        }

        $stmt = $pdo->prepare($sql);
        foreach ($filters as $filter => $value) {
            $dataType = PDO::PARAM_STR;

            if ($filter == 'id') {
                $dataType = PDO::PARAM_INT;
            } elseif ($filter == 'data') {
                return false;
            }

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
