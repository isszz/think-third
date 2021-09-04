<?php

namespace isszz\third;

use ArrayAccess;
use JsonSerializable;

use isszz\third\exception\InvalidArgumentException;
use isszz\third\interfaces\AccessTokenInterface;
use isszz\third\traits\Attr;

class AccessToken implements AccessTokenInterface, ArrayAccess, JsonSerializable
{
    use Attr;

    /**
     * AccessToken constructor.
     *
     * @param array $attrs
     */
    public function __construct(array $attrs)
    {
        if (empty($attrs['access_token'])) {
            throw new InvalidArgumentException('The key "access_token" could not be empty.');
        }
        
        $this->attrs = $attrs;
    }

    /**
     * Return the access token string.
     *
     * @return string
     */
    public function getToken()
    {
        return $this->getAttr('access_token');
    }

    public function __toString()
    {
        return strval($this->getAttr('access_token', ''));
    }
    
    public function jsonSerialize()
    {
        return $this->getToken();
    }
}