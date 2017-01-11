<?php

namespace Core\Http;

/**
 * Http Response
 */
class HttpResponse
{
    /**
     * Response headers
     * @var array
     */
    public $headers;

    /**
     * Response code
     * @var integer
     */
    public $code;

    /**
     * Data
     * @var string
     */
    public $responseData;

    /**
     * Constructs an instance with properties
     *
     * @param array   $headers Response headers.
     * @param integer $code    Response code.
     * @param string  $data    Data.
     */
    public function __construct(array $headers, int $code, string $data)
    {
        $this->headers = $headers;
        $this->code  = $code;
        $this->data  = $data;
    }
}
