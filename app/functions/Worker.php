<?php

$worker = new Worker();
class Worker extends Controller
{

    public function success($worker_job_id)
    {
        $update = self::db()->prepare("DELETE FROM `queue` WHERE `id` = :id");
        $update->execute(array(":id" => $worker_job_id));
    }

}