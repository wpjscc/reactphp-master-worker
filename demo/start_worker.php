<?php 

require __DIR__ . '/../vendor/autoload.php';

use Wpjscc\MasterWorker\Worker;
use Wpjscc\MasterWorker\ConnectionManager;
use Wpjscc\MasterWorker\WorkerClient;
use React\Socket\ConnectionInterface;

$worker = Worker::instance();

$worker->on('workerOpen', function () {
    echo 'workerOpen' . PHP_EOL;
});

$worker->on('clientOpen', function ($_id, $data) {
    echo 'clientOpen' . PHP_EOL;
});

$worker->on('clientMessage', function ($_id, $message) {
    echo 'clientMessage' . PHP_EOL;
    WorkerClient::instance()->sendToClient($_id, $message);
});

$worker->on('clientClose', function ($_id, $data) {
    echo 'clientClose' . PHP_EOL;
});

$worker->run();

