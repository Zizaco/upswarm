<?php

namespace Core;

class Runner {

    public function __construct() {
        pcntl_signal(SIGCHLD, SIG_IGN);
    }

    public function go() {
        if (pcntl_fork()) return;
        $args = func_get_args();
        $func = array_shift($args);
        call_user_func_array($func, $args);
        die;
    }
}
