<?php 

namespace Wpjscc\MasterWorker\Traits;

use React\Socket\ConnectionInterface;
use Clue\React\NDJson\Encoder;

trait Write 
{
    public function write(ConnectionInterface $connection, $data)
    {
        (new Encoder($connection))->write($data);
    }

}