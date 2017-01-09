<?php

namespace Core;

class Channel {

    protected $size;
    protected $in;
    protected $out;

    public function __construct($size = 8192) {
        $this->size = $size;
        list($this->in, $this->out) = stream_socket_pair(
            STREAM_PF_UNIX,
            STREAM_SOCK_DGRAM,
            STREAM_IPPROTO_IP
        );
    }

    public function __destruct()
    {
        fclose($this->in);
        fclose($this->out);
    }

    public function read() {
        return unserialize(fread($this->in, $this->size));
    }

    public function write($data) {
        return fwrite($this->out, serialize($data));
    }
}
