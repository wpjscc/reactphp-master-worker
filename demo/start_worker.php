<?php 

require __DIR__ . '/../vendor/autoload.php';

use Wpjscc\MasterWorker\Worker;
use React\Socket\ConnectionInterface;

$worker = Worker::instance();

$worker->on('workerOpen', function (ConnectionInterface $connection) {
    echo 'workerOpen' . PHP_EOL;
});

$worker->on('clientOpen', function ($clientId, $data) {
    echo 'clientOpen' . PHP_EOL;
});
$worker->on('clientMessage', function ($clientId, $data) {
    echo 'clientMessage' . PHP_EOL;
});

$worker->on('clientClose', function ($clientId, $data) {
    echo 'clientClose' . PHP_EOL;
});

$worker->run();

