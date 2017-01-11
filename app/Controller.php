<?php
namespace App;

use Core\Http\HttpResponse;
use Core\Message;
use Core\Service;
use React\EventLoop\LoopInterface;
use React\Stream\Stream;
use PDO;
use PDOException;

class Controller extends Service
{
    protected $dbConn;

    /**
     * Provide the given service. This is the initialization point of the
     * service, the initialization point of the service.
     *
     * @param  LoopInterface $loop ReactPHP loop.
     *
     * @return void
     */
    public function serve(LoopInterface $loop)
    {
        $this->dbConn = new PDO('mysql:host=127.0.0.1;dbname=benchmark', 'app', 'secret');
    }

    public function handleMessage(Message $message, LoopInterface $loop)
    {
        $httpRequest = $message->getData();
        $actionName  = $httpRequest->action;

        $response = $this->$actionName($httpRequest->request, ...array_values($httpRequest->params));

        $message->respond(new Message($response));
    }

    public function users()
    {
        $data = [];
        foreach($this->dbConn->query('SELECT * FROM users LIMIT 25', PDO::FETCH_ASSOC) as $row) {
            $data[] = $row;
        }
        $data = json_encode($data);

        return new HttpResponse(['Content-Type' => 'application/json; charset=utf-8', 'Content-Length' => strlen($data), 'Connection' => 'close'], 200, $data);
    }

    public function hello($request, $name = "world")
    {
        return new HttpResponse(['Content-Type' => 'text/plain'], 200, "Hellow! $name");
    }

    public function world($request)
    {
        return new HttpResponse(['Content-Type' => 'text/plain'], 200, "Worldah!");
    }
}
