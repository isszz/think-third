<?php

namespace isszz\third\exception;

class AuthorizeFailedException extends Exception
{
    /**
     * Response body.
     *
     * @var array
     */
    public array $body;
    
    /**
     * Constructor.
     *
     * @param string $message
     * @param array  $body
     */
    public function __construct(string $message, $body)
    {
        parent::__construct($message, -1);

        $this->body = (array) $body;
    }
}