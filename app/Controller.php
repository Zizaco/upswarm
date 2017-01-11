<?php
namespace App;

use Core\Http\HttpResponse;
use Core\Message;
use Core\Service;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;

class Controller extends Service
{
    public function handleMessage(Message $message, LoopInterface $loop)
    {
        $httpRequest = $message->getData();
        $actionName  = $httpRequest->action;

        $response = $this->$actionName($httpRequest->request, ...array_values($httpRequest->params));

        $message->respond(new Message($response));
    }

    public function hello($request, $name = "world")
    {
        return new HttpResponse([], 200, "Hellow! $name");
    }

    public function world($request)
    {
        return new HttpResponse([], 200, "Worldah!");
    }
}
