<?php

namespace Upswarm;

use Upswarm\Exceptions\ServiceException;
use Upswarm\Exceptions\UnserializableMessageDataException;
use Exception;
use React\Promise\Deferred;
use React\Promise\Promise;
use stdClass;

/**
 * The Upswarm comunication unit used with the supervisor and between services.
 * The nature of messaging enchangement is based in this Message structure.
 */
final class Message
{
    /**
     * Unique message identifier
     * @var string
     */
    public $id;

    /**
     * Type of the $data within the Message. Name of the class or type of
     * the primitive.
     * @var string
     */
    protected $type = 'null';

    /**
     * The data wrapped within the message. The data MUST be serializable in
     * order to be sent to other services.
     * @var string
     */
    protected $data;

    /**
     * The name of the service class that has created the message. If the
     * message expects an response, this is the destination of the response.
     * @var string
     */
    public $sender;

    /**
     * The "identification" of the service that this message is addressed to.
     * It can be the name of a service class or an id.
     * @var string
     */
    public $recipient;

    /**
     * Defered that may be used to resolve an promise if the message expects
     * an response.
     * @var Deferred
     */
    protected $deferred;

    /**
     * EventEmitter instance of the service that is currently reading the
     * message. This attribute is cleared before sending the message. It is
     * used to indicate whenever a Message as been responded.
     * @var \use Evenement\EventEmitter;
     */
    public $eventEmitter;

    /**
     * Initialize a new Message instance.
     *
     * @throws UnserializableMessageDataException If $data is not serializable.
     *
     * @param mixed  $data      Data that will be transported by the message.
     * @param string $recipient Message destination. null means that the supervisor is the recipient.
     */
    public function __construct($data, string $recipient = null)
    {
        $this->id = uniqid('', true);
        $this->recipient = $recipient;
        $this->setData($data);
    }

    /**
     * Sets the given $data as the data that will be transported by the Message.
     *
     * @throws UnserializableMessageDataException If $data is not serializable.
     *
     * @param mixed $data Anything that is serializable.
     *
     * @return self
     */
    public function setData($data)
    {
        if (is_object($data)) {
            $this->type = get_class($data);
        } else {
            $this->type = gettype($data);
        }

        try {
            $this->data = serialize($data);
        } catch (Exception $e) {
            if (! $e instanceof Exception) {
                throw new UnserializableMessageDataException($data, 0, $e);
            }
            $this->data = serialize(new ServiceException(get_class($e).' '.$e->getMessage(), $e->getCode(), $e));
        }

        return $this;
    }

    /**
     * Gets the $data of the Message.
     * WARNING: It will return a new instance everytime it is called. So, don't
     * use this method inside loops. Ideally 'popData' should not be called more
     * than once.
     *
     * @return mixed
     */
    public function getData()
    {
        return unserialize($this->data);
    }

    /**
     * Gets the type of the Message data. The type of the return of 'getData'
     * method.
     *
     * @return string Type of data within the Message.
     */
    public function getDataType(): string
    {
        return $this->type;
    }

    /**
     * If called, the message is marked as "waiting a response". With this, the
     * service that is sending the request will register a callback
     * (the deferred) that will be triggered whenever the response of the
     * message arrives back to it.
     *
     * @return \React\Promise\Promise Promise that will be resolved when the response arrives or if the timeout is reached.
     */
    public function getPromise(): Promise
    {
        if (! $this->deferred) {
            $this->deferred = new Deferred();
        }

        return $this->deferred->promise();
    }

    /**
     * Returns the deferred of the response.
     *
     * @return Deferred
     */
    public function getDeferred(): Deferred
    {
        return $this->deferred;
    }

    /**
     * Tells if this command expects an response. Which will be true if the
     * getPromise was called before.
     *
     * @return boolean
     */
    public function expectsResponse(): bool
    {
        return null != $this->deferred;
    }

    /**
     * Sends a response Message back to the original $sender service.
     *
     * @param  Message $message Message that will be sent as a response to this message.
     *
     * @return void
     */
    public function respond(Message $message)
    {
        // The identification should be the same in order that the service
        // receiving the message will know that $message is responding to the
        // current Message.
        $message->id = $this->id;
        $message->recipient = $this->sender;

        if ($this->eventEmitter) {
            $this->eventEmitter->emit('respond', [$message]);
        }
    }

    /**
     * Get an signatura that can be used for comparison between Messages in
     * order to learn if they are the same.
     * @return string
     */
    public function getSignature()
    {
        return md5(sprintf('%s:%s:%s', $this->type, $this->data, $this->recipient));
    }

    /**
     * Tells which Fields should not be discarted when serializing a message.
     *
     * @return array Field that will not be serialized
     */
    public function __sleep()
    {
        return [
            'id',
            'type',
            'data',
            'sender',
            'recipient',
        ];
    }

    /**
     * Returns an string representation of the Message.
     *
     * @return string String representation.
     */
    public function __toString()
    {
        return json_encode([
            'id' => $this->id,
            'type' => $this->type,
            'sender' => $this->sender,
            'recipient' => $this->recipient,
        ]);
    }
}
