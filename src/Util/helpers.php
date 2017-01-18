<?php

if (! function_exists('waitfor')) {
    /**
     * Wait for the given promisse to be resolved.
     *
     * @param  \React\Promise\Promise ...$promisse One or more promisses to be resolved.
     *
     * @return mixed Promisse done value or array of done values (if multiple promisses were passed).
     */
    function waitfor(\React\Promise\Promise ...$promisse)
    {
        global $_serviceLoop;

        if (count($promisse) == 1) {
            return \Clue\React\Block\await($promisse[0], $_serviceLoop, 30);
        }

        return \Clue\React\Block\awaitAll($promisse, $_serviceLoop, 30);
    }
}
