<?php
namespace Upswarm\FunctionalTest\Fixtures;

use Exception;
use React\EventLoop\LoopInterface;
use Upswarm\Message;
use Upswarm\Service;

/**
 * Fixture to test exchangement of messages between services.
 */
class PingRespondService extends Service
{
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
        $loop->addTimer(1, function () {
            $this->sendNumberToPong(1);
        });
    }

    /**
     * Handles the messages that are received by this service.
     *
     * @param  Message       $message Incoming message.
     * @param  LoopInterface $loop    ReactPHP loop.
     *
     * @throws Exception In case of unknow message.
     *
     * @return void
     */
    public function handleMessage(Message $message, LoopInterface $loop)
    {
    }

    /**
     * Send the given number to PongRespondService
     *
     * @param  integer $number Number to be sent.
     *
     * @return void
     */
    public function sendNumberToPong(int $number)
    {
        $message = new Message($number, PongRespondService::class);
        $promise = $message->getPromise();
        $this->sendMessage($message);
        echo "Ping sent $number\n";

        $promise->done(function (Message $response) {
            $number = $response->getData();
            echo "Ping received response $number\n";

            if ($number < 5) {
                $this->sendNumberToPong($number+1);
            }
        });
    }
}
