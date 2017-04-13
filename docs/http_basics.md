## Http Server

Upswarm provides a HTTP server implementation using [ReactPHP HTTP package](https://github.com/reactphp/http).

`Upswarm\Util\Http\HttpServer` is an abstraction that will handle requests and dispatch messages containing `Upswarm\Util\Http\HttpRequest` to specified _Services_.

```php
class MyHttpService extends Service
{
    public function serve(LoopInterface $loop)
    {
        $server = new HttpServer($this);

        $server->routes(function ($router) {
            $router->get(
                '/hello/{name}', 'MyApp\\MyFirstService@actionName'
            );
        });

        $server->listen(8081);
    }
}
```

### Handling errors

It's possible to register a _Service_ as the error handler using `errorHandler` method. By doing this, the errors messages will be sent to registered error handler _service_.

```php
$server->errorHandler('MyApp\\MyHttpErrorHandler');
```

```php
class MyHttpErrorHandler extends Service
{
    public function handleMessage(Message $message, LoopInterface $loop)
    {
        $message->getDataType(); // Exception

        $httpResponse = new HttpResponse(
            [],
            500,
            "Internal server error"
        );

        $message->respond(new Message($httpResponse));
    }
}
```

### Handling requests

It's recomended that you handle HttpRequests using `Upswarm\Util\Http\ControllerService`, but you can handle HttpRequests manually doing the following:

```
class MyFirstService extends Service
{
    public function handleMessage(Message $message, LoopInterface $loop)
    {
        $message->getDataType(); // Upswarm\Util\Http\HttpRequest

        $upswarmRequest = $message->getData();

        $upswarmRequest->request // Psr7 RequestInterface
        $upswarmRequest->action // "actionName"
        $upswarmRequest->params // ['name' => 'fooBar']

        $httpResponse = new HttpResponse(
            [],
            200,
            "Hello ".{$upswarmRequest->params['name'];
        );

        $message->respond(new Message($httpResponse));
    }
}
```

## Controller services

`Upswarm\Util\Http\ControllerService` is an abstract controller implementation that helps handling HttpRequests.

By using the _ControllerService_ the _Responses_ will be generated automatically if you return a _string_ or an _array_ (`text/html` or `application/json` respectivelly) from the actions.

The code bellow shows how to handle requests in the same way as showed above extending _ControllerService_:

```php
use RingCentral\Psr7\Request;
use Upswarm\Util\Http\ControllerService;

class MyFirstController extends ControllerService
{
    public function hello(Request $request, string $name = 'world')
    {
        return "Hello $name!";
        // or
        new HttpResponse(
            ['Custom' => 'Header'],
            200,
            "Hello $name";
        );
    }
}
```

The _ControllerService_'s `handleMessage` will call the method in `$upswarmRequest->action` under the hood.
