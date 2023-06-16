<?php

namespace Wpjscc\MasterWorker\Traits;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Wpjscc\MasterWorker\Client;

trait HttpServer 
{
    // $key is register or master
    public function runHttpServer($key)
    {

        putenv("X_LISTEN=".(getParam('--http-server') ?: '0.0.0.0:8080'));

        $app = new \FrameworkX\App();

        $app->get('/', function () {
            return Response::plaintext(
                "Hello wörld!\n"
            );
        });

        $app->get('/users/{name}', function (ServerRequestInterface $request) {
            return Response::plaintext(
                "Hello " . $request->getAttribute('name') . "!\n"
            );
        });

        $app->map(['GET', 'POST'], '/events/{event}', function (ServerRequestInterface $request) use ($key) {
            $params = $request->getQueryParams();
            $event = $request->getAttribute('event');
            $events = explode(',', $event);
            $methodToParams = [];
            $extra = [];
            foreach ($events as $method) {
                if (method_exists(Client::instance($key), $method)) {
                    $className = get_class(Client::instance($key));
                    $rp = new \ReflectionClass($className);
                    $methodParameters = [];
                    $rpParameters = $rp->getMethod($method)->getParameters();
                    foreach ($rpParameters as $rpParameter) {
                        $name = $rpParameter->getName();
                        $position = $rpParameter->getPosition();
                        if (isset($params[$method][$name])) {
                            $methodParameters[$position] = $params[$method][$name];
                        } else {
                            if ($rpParameter->isOptional()) {
                                $methodParameters[$position] = $rpParameter->getDefaultValue();
                            } else {
                                return  Response::json([
                                    'code' => 1,
                                    'msg' => "方法 $method 缺少 $name 参数",
                                    'data' => []
                                ]);
                            }
                        }
                    }
                    $methodToParams[$method] = $methodParameters;
                } else {
                    $extra[] = "方法 $method 不存在 或 缺少参数";
                }
            }


            if (empty($methodToParams)) {
                return Response::json([
                    'code' => 1,
                    'msg' => implode(',', $extra),
                    'data' => []
                ]);
            }
            
            $data = [];
            foreach ($methodToParams as $m => $p) {
                $data[$m] = Client::instance($key)->{$m}(...$p);
            }

            return $this->getJsonPromise($data)->then(function ($data) use ($extra) {
                return Response::json([
                    'code' => 0,
                    'msg' => 'ok',
                    'extra' => $extra,
                    'data' => $data
                ]);
            });
        });

        $app->run();
    }
}