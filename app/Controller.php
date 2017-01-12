<?php
namespace App;

use Core\Util\Http\Controller as BaseController;
use Core\Util\Http\HttpResponse;
use PDO;
use PDOException;
use React\EventLoop\LoopInterface;

class Controller extends BaseController
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

    public function users()
    {
        $data = [];
        $queryResult = $this->dbConn->query('SELECT * FROM users LIMIT 25', PDO::FETCH_ASSOC);

        foreach($queryResult as $row) {
            $data[] = $row;
        }

        return $data;
    }

    public function hello()
    {
        return "Hello world";
    }
}
