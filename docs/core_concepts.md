## Introduction

An Upswarm application can be multi-processed, asynchronous, fault-tolerant due to it's nature of having each _Service_ running as a separated process.

The building blocks of an Upswarm application are:

- Supervisors
- Services
- Messages

An Upswarm application is composed by one or more **Supervisors** (one per server) and a number of different **Services** that exchange **Messages** to acomplish a goal. The framework leverage the benefits (and the challenges) of _Distributed Computing_ and of _Microservice Architecture_.

## Supervisor

The _Supervisor_ is a process that orchestrate other processes (_Services_) and the message exchanging between then in a single server. Ideally only one _Supervisor_ process should run per server.

You can spin a _Supervisor_ with the `serve` command.

    php upswarm serve

By default the `serve` command will look for a **topology file** (`topology.json` by default) that will tell which _Services_ instances should be ideally running in that server. After that, the _Supervisor_ will spawn those _Services_ and make sure that they are always up and responding.

!!! Note

    If you update the **topology file** while the _Supervisor_ is running it will automatically kill or spawn _Service_ instances to match the target topology.

Another responsability of the _Supervisor_ is to ensure that _Messages_ are delivered. The _Supervisor_ will automatically handle the _service discovery_ process, so that each _Service_ will know how to deliver messages to other services.

## Services

In an Upswarm application, a _Service_ is a process that can:

1. Contain code implementation
2. handle incoming _Messages_ 
3. send _Messages_

_Services_ are the main building blocks of an application. _Services_ can have all sorts of responsabilities and can scale idependently of each other. Each server can have multiple instances of the same type of _Service_ running in order to have redundancy to do load balancing. Of a _Message_ is addressed to an type of _Service_, it will be automatically balanced between all instances of that type.

Since _Services_ can have all sorts of responsabilities. To delimit those responsabilities and split the Business Domain between multiple services is crucial when building a _Distributed Application_.

A _Service_ is basically a class that extends `Upswarm\Service`:

```php
namespace MyApp;

use React\EventLoop\LoopInterface;
use Upswarm\Message;
use Upswarm\Service;

class MyFirstService extends Service
{
    public function serve(LoopInterface $loop)
    {
        // Bootstrap
    }

    public function handleMessage(Message $message, LoopInterface $loop)
    {
        // Message handling
    }
}
```

There is two ways of running a _Service_. Manually, using the `spawn` command:

    php upswarm spawn MyApp\\MyFirstService

Or using the **topology file** (`topology.json` by default). For example, the topology file bellow tells the _Supervisor_ to keep at least 4 instances of `MyApp\MyFirstService` up and running:

```json
// topology.json
{
    "settings": {
        "port": 8300
    },
    "services": {
        MyApp\\MyFirstService: 4
    }
}

```

## Messages

Distributed applications (like the ones you build using Upswarm) don't share memory (variables and state) between processes (_Services_). All the coordination and comunication between _Services_ are done by _Message_ exchangement.

**Creating Messages**

In order to create a _Message_ all you have to do is create a new instance:

```php
new Message(mixed $data, string $recipient);
```

For example. In order to create a _Message_ envelope with a `stdClass` instance containing the `foo="bar"` property to `MyApp\\MyFirstService` _service_ we can do the following:

```php
$info = new stdClass;
$info->foo = "bar";

$message = new Message($info, 'MyApp\\MyFirstService');
```

**Sending Messages**

Upswarm _Services_ can send _Messages_ in four ways:

```php
// Helper function
send_message($message);

// Upswarm\MessageSender::send (static method)
MessageSender::send($message);

// Upswarm\MessageSender::sendMessage (instance method)
$messageSenderInstance->sendMessage($message);

// Upswarm\Service::sendMessage (method of the Service)
$this->sendMessage($message); 
```

!!! Note

    All forms if sending messages are testable. Upswarm always calls the non-static `$messageSenderInstance->sendMessage($message);` method under the hood. Also the main `MessageSender` instance that is used can be replaced with a mock using testing facilities that are provided.

**Responding to Messages**

Since _Messages_ that are sent are automatically balanced between _Service_ instances, it isn't possible to know to which instance exactly each _Message_ will be delivered.

For example, the following code will not ensure that the response will be delivered to the same _Service_ instance that sent the question in the first place:

```php
// class that extends Upswarm\Service
public function handleMessage(Message $message, LoopInterface $loop)
{
    if ($message->getDataType() == "MyApp\Question") {
        $responseObj = new Response(42);
        // Will not ensure that the message will go to the correct instance
        send_message(new Message($responseObj), 'MyApp\MyFirstService');
    }
}
```

In order to send a _Message_ back to the exact same _Service_ we can use the `respond` method. With that in mind, the above code would be:

```php
// class that extends Upswarm\Service
public function handleMessage(Message $message, LoopInterface $loop)
{
    if ($message->getDataType() == "MyApp\Question") {
        $response = new Response(42);
        // Respond to the exact same service instance that sent $message
        $message->respond(new Message($responseObj));
    }
}
```

**Handling Message Responses**

Whenever a _Service_ responds to a _Message_, like in the example above. The message can be handled by the `handleMessage` method.

It is also possible to handle _Message_ responses using **promises**:

```php
$message = new Message(5, 'MyApp\IntegerDoublerService');
$promise = $message->getPromise(); // React\Promise

send_message($message);
```

A promise can be handled using callbacks, like in javascript:

```php
$promise->then(function (Message $response) {
    // Do something with $response
}, function ($error) {
    // Do something with the Exception $error
});
```

But it can also be handled without callbacks using Upswarm's `waitfor`. Which is similar to Javascript's `await`. This can be seen as a strength: you’re able to leverage `try / catch` conventions and have a readable synchronous-looking style whith the benefits of asynchronous code execution (non-blocking IO):

```php
try {
    $response = waitfor($promise);
} catch (Exception $e) {
    // Do something with the Exception
}
// Do something with $response
```

The `waitfor` can also handle multiple promisses, which allows _Messages_ to be handled concurrently.

```php
// Messages being handled concurrently
list('userResp', 'subscriptionResp', 'permissionResp') = waitfor(
    $getUserMessage,
    $getSubscriptionMessage,
    $getPermissionMessage
);

if (true == $permissionResp->getData()) {
    $this->upgradeSubscription($userResp->getData(), $subscriptionResp->getData());
}
```

!!! Note

    Upswarm's `waitfor` handles promisses asynchronously under the hood. It means that even thought the code looks synchronous, it will not block the process, allowing the _Service_ process to handle other _Messages_ and to do other processing while waiting the promisse to be resolved! (non-blocking IO)
