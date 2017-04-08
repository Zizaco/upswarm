<?php

if (! function_exists('waitfor')) {
    /**
     * Wait for the given promise to be resolved.
     *
     * @param  \Upswarm\Message|\React\Promise\Promise ...$promise One or more promises to be resolved.
     *
     * @throws \Upswarm\Exceptions\ServiceException If an invalid parameter type is passed.
     *
     * @return mixed Promise done value or array of done values (if multiple promises were passed).
     */
    function waitfor(...$promise)
    {
        global $_serviceLoop;
        global $_service;

        // In order to make sure that ZMQ will trigger events while waiting
        // the promises to be fulfilled.
        $_serviceLoop->nextTick(function () use ($_service) {
            $_service->forceSocketTick();
        });

        // If Message instances have been passed waitfor will get their
        // promisses and send then messages.
        foreach ($promise as $key => $value) {
            if ($value instanceof \Upswarm\Message) {
                $promise[$key] = $value->getPromise();
                send_message($value);
            }

            if ($promise[$key] instanceof \React\Promise\Promise) {
                throw new \Upswarm\Exceptions\ServiceException("'waitfor' parameters must be 'Upswarm\Message' or 'React\Promise\Promise' instances", 107);
            }
        }

        if (count($promise) == 1) {
            return \Clue\React\Block\await($promise[0], $_serviceLoop, 30);
        }

        return \Clue\React\Block\awaitAll($promise, $_serviceLoop, 30);
    }
}

if (! function_exists('send_message')) {
    /**
     * Sends message to Supervisor or to another service. The main inter process
     * comunication mechanism of Upswarm.
     *
     * @param  \Upswarm\Message $message Message to be sent.
     *
     * @return Message
     */
    function send_message(\Upswarm\Message $message)
    {
        global $_service;

        return $_service->sendMessage($message);
    }
}
