<?php

namespace Upswarm;

use Upswarm\Instruction\Identify;
use Upswarm\Message;
use Evenement\EventEmitter;
use React\Dns\Resolver\Factory as DnsResolver;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\SocketClient\Connector;
use React\Stream\Stream;

/**
 * Component that handles the procedure of sending messages to the Supervisor
 * or other services.
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
     * @var \React\ZMQ\SocketWrapper
     */
    protected $supervisorConnectionOutput;

    /**
     * Service event emitter
     * @var EventEmitter
     */
    protected $eventEmitter;

    /**
     * Stores a history of signatures of exchanged messages that had a Response.
     * This is used in order to predict the Response for similar frequent
     * Messages.
     * @var array
     */
    protected $exchangedMessages = [];

    /**
     * Injects dependencies
     * @param Service $service Service that will send the Messages.
     */
    public function __construct(Service $service)
    {
        $this->service      = $service;
        $this->eventEmitter = $service->getEventEmitter();
        $this->registerPredictionTimeframe();
    }

    /**
     * Do the preparation of a Message object that will be sent. The preparation
     * process consists of the registration of the callback and the resolution
     * of the Deferred of the message.
     *
     * @param  Message $message Message being prepared to be sent.
     *
     * @return boolean Response of message could be predicted.
     */
    protected function prepareForResponse(Message $message)
    {
        // Identify that this service should receive the response message.
        $message->sender = $this->service->getId();
        $signature = $message->getSignature();

        if (isset($this->exchangedMessages[$signature]) && is_object($this->exchangedMessages[$signature])) {
            $this->resolveDeferred($message, $this->exchangedMessages[$signature]);
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
        if (! $this->supervisorConnectionOutput) {
            $this->supervisorConnectionOutput = $this->service->getSupervisorConnection();
        }

        // On the next tick of the loop
        $this->service->getLoop()->nextTick(function () use ($message) {

            // Register callback to fullfill promisse if Message has deferred.
            if ($message->expectsResponse()) {
                if ($this->prepareForResponse($message)) {
                    $this->service->getLoop()->addTimer(5, function () use ($message) {
                        $recipientAddress  = $message->recipient ?: '';
                        $senderAddress     = $message->sender ?: '';
                        $serializedMessage = serialize($message);

                        $this->supervisorConnectionOutput->sendmulti([$recipientAddress, $senderAddress, $serializedMessage]);
                    });
                    return;
                }
            }

            $recipientAddress  = $message->recipient ?: '';
            $senderAddress     = $message->sender ?: '';
            $serializedMessage = serialize($message);

            // Sends message to the supervisor.
            $this->supervisorConnectionOutput->sendmulti([$recipientAddress, $senderAddress, $serializedMessage]);
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
        // Add a timeout to reject the promisse of the message.
        $timeout = $this->service->getLoop()->addTimer(10, function () use ($message) {
            $message->getDeferred()->reject(new Message("Timeout. Service did not responded within 10 seconds."));
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

    /**
     * Resolve the Deferred of the $message with the given $response.
     *
     * @param  Message $message  Message to have it's deferred resolved.
     * @param  Message $response Message that will be used as the response.
     *
     * @return void
     */
    protected function resolveDeferred(Message $message, Message $response)
    {
        $message->getDeferred()->resolve($response);
    }

    /**
     * Take into account that a Message and a Response with the same signatures
     * have been exchanged in order to be able to predict future responses.
     *
     * @param  string  $signature Signature of th Message being sent.
     * @param  Message $response  Message that was received as a response.
     *
     * @return void
     */
    protected function countForPrediction(string $signature, Message $response)
    {
        $responseSignature = $response->getSignature();

        if (isset($this->exchangedMessages[$signature]) && is_array($this->exchangedMessages[$signature])) {
            if (! isset($this->exchangedMessages[$signature][$responseSignature])) {
                $this->exchangedMessages[$signature][$responseSignature] = 0;
            }

            $this->exchangedMessages[$signature][$responseSignature]++;

            if (count($this->exchangedMessages[$signature]) == 1 && $this->exchangedMessages[$signature][$responseSignature] >= static::RESPONSE_PREDICTION_THRESHOLD) {
                $this->exchangedMessages[$signature] = $response;
            }
        }
    }

    /**
     * Register an periodic procedure of decreasing the counter of messages
     *
     * @return void
     */
    protected function registerPredictionTimeframe()
    {
        $this->service->getLoop()->addPeriodicTimer(static::RESPONSE_PREDICTION_TIMEFRAME, function () {
            foreach ($this->exchangedMessages as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $this->exchangedMessages[$key][$subKey] = ((int)$subValue) - 1;
                        if ($subValue <= 0) {
                            unset($this->exchangedMessages[$key][$subKey]);
                        }
                    }
                    if (empty($value)) {
                        unset($this->exchangedMessages[$key]);
                    }
                }

                if (is_object($value)) {
                    $this->exchangedMessages[$key] = [$value->getSignature() => static::RESPONSE_PREDICTION_THRESHOLD - 1];
                }
            }
        });
    }
}
