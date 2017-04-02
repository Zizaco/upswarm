<?php

if (! function_exists('waitfor')) {
    /**
     * Wait for the given promise to be resolved.
     *
     * @param  \React\Promise\Promise ...$promise One or more promises to be resolved.
     *
     * @return mixed Promise done value or array of done values (if multiple promises were passed).
     */
    function waitfor(\React\Promise\Promise ...$promise)
    {
        global $_serviceLoop;
        global $_service;

        // In order to make sure that ZMQ will trigger events while waiting
        // the promises to be fulfilled.
        $_serviceLoop->nextTick(function () use ($_service) {
            $_service->forceSocketTick();
        });

        if (count($promise) == 1) {
            return \Clue\React\Block\await($promise[0], $_serviceLoop, 30);
        }

        return \Clue\React\Block\awaitAll($promise, $_serviceLoop, 30);
    }
}
