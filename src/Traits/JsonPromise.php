<?php 

namespace Wpjscc\MasterWorker\Traits;

use React\Promise\Deferred;
use WyriHaximus\React\Stream\Json\JsonStream;

trait JsonPromise
{
    public function getJsonPromise($array = [])
    {
        $deferred = new Deferred();
        $buffer = '';
        $jsonStream = new JsonStream();
        $jsonStream->on('data', function($data) use (&$buffer) {
            $buffer .= $data;
        });

        $jsonStream->on('end', function () use (&$buffer,$deferred){
            $deferred->resolve(json_decode($buffer, true));
            $buffer = '';
        });

        $jsonStream->end($array);
        return $deferred->promise();
    }   
}
