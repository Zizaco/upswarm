<?php
namespace Upswarm\FunctionalTest\Fixtures;

use App\HelloWorld\GreetingService;
use React\EventLoop\LoopInterface;
use RingCentral\Psr7\Request;
use Upswarm\Message;
use Upswarm\Util\Http\ControllerService;

class HelloWorldController extends ControllerService
{
    public function hello(Request $request, string $name = 'world')
    {
        return "Hello $name!";
    }
}
