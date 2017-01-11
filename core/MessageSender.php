<?php

namespace Core;

use Core\Instruction\Identify;
use Core\Message;
use Evenement\EventEmitter;
use React\Dns\Resolver\Factory as DnsResolver;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\SocketClient\Connector;
use React\Stream\Stream;

/**
 * An small service class that aims to encapsulate the logic regarding sending
 * an Message to the Supervisor
 */
class MessageSender
{
    const RESPONSE_PREDICTION_THRESHOLD = 5;
    const RESPONSE_PREDICTION_TIMEFRAME = 2;

    /**
     * Upswarm service.
     * @var Service
     */
    protected $service;

    /**
     * Socket to comunicate with the Supervisor
     * @var Stream
     */
    protected $supervisorConnection;

    /**
     * Service event emitter
     * @var EventEmitter
     */
    private $eventEmitter;

    /**
     * Stores the signature of the messages and the amount
     * @var array
     */
    protected $exchangedMessages = [];

    /**
     * Injects dependencies
     * @param Service $service Service that will send the Messages.
     */
    public function __construct(Service $service)
    {
        $this->service = $service;
        $this->eventEmitter = $service->getEventEmitter();
        $this->registerPredictionTimeframe();
    }

    protected function prepareForResponse(Message $message)
    {
        $signature = $message->getSignature();

        if (isset($this->exchangedMessages[$signature]) && is_object($this->exchangedMessages[$signature])) {
            $this->resolveDefered($message, $this->exchangedMessages[$signature]);
            return true;
        } elseif (! isset($this->exchangedMessages[$signature])) {
            $this->exchangedMessages[$signature] = [];
        }

        $this->registerDeferredCallback($message, $signature);
    }

    /**
     * Sends message to Supervisor or to another service. The main inter process
     * comunication mechanism of Upswarm.
     *
     * @param  Message $message Message to be sent.
     *
     * @return Message
     */
    public function sendMessage(Message $message)
    {
        if (! $this->supervisorConnection) {
            $this->supervisorConnection = $this->service->getSupervisorConnection();
        }

        // On the next tick of the loop
        $this->service->getLoop()->nextTick(function () use ($message) {
            // Register callback to fullfill promisse if Message has deferred.
            if ($message->expectsResponse()) {
                if ($this->prepareForResponse($message)) {
                    return;
                }
            }

            // Sends message to the supervisor.
            $this->supervisorConnection->write(serialize($message));
        });

        return $message;
    }

    /**
     * Registers callbacks to Resolve or Reject the deferred of the message.
     *
     * @param  Message $message          Message that expects a response.
     * @param  string  $messageSignature Message signature.
     *
     * @return void
     */
    private function registerDeferredCallback(Message $message, string $messageSignature)
    {
        // Identify that this service should receive the response message.
        $message->sender = $this->service->getId();

        // Add a timeout to reject the promisse of the message.
        $timeout = $this->service->getLoop()->addTimer(10, function () use ($message) {
            $message->getDeferred()->reject();
            $this->eventEmitter->removeAllListeners($message->id);
        });

        // Registers callback to resolve promisse if a response message is received.
        $this->eventEmitter->on($message->id, function ($value) use ($message, $timeout, $messageSignature) {
            $timeout->cancel();
            $this->eventEmitter->removeAllListeners($message->id);
            $this->countForPrediction($messageSignature, $value);
            $message->getDeferred()->resolve($value);
        });
    }

    protected function resolveDefered(Message $message, Message $response)
    {
        $message->getDeferred()->resolve($response);
    }

    protected function countForPrediction($signature, Message $response)
    {
        $responseSignature = $response->getSignature();

        if (isset($this->exchangedMessages[$signature]) && is_array($this->exchangedMessages[$signature])) {
            if (! isset($this->exchangedMessages[$signature][$responseSignature])) {
                $this->exchangedMessages[$signature][$responseSignature] = 0;
            }

            $this->exchangedMessages[$signature][$responseSignature]++;

            if ($this->exchangedMessages[$signature][$responseSignature] >= static::RESPONSE_PREDICTION_THRESHOLD) {
                $this->exchangedMessages[$signature] = $response;
            }
        }
    }

    protected function registerPredictionTimeframe()
    {
        $this->service->getLoop()->addPeriodicTimer(static::RESPONSE_PREDICTION_TIMEFRAME, function () {
            unset($this->exchangedMessages);
            $this->exchangedMessages = [];
        });
    }
}
