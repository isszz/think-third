<?php

namespace isszz\third;

use ArrayAccess;
use JsonSerializable;

use isszz\third\traits\Attr;
use isszz\third\interfaces\UserInterface;
use isszz\third\interfaces\DriverInterface;

class User implements ArrayAccess, UserInterface, JsonSerializable, \Serializable
{
    use Attr;

    /**
     * @var \isszz\third\interfaces\DriverInterface|null
     */
    protected ?DriverInterface $driver;

    public  function __construct(array $attrs, DriverInterface $driver = null)
    {
        $this->attrs = $attrs;
        $this->driver = $driver;
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return string
     */
    public function getId()
    {
        return $this->getAttr('id');
    }

    /**
     * Get the username for the user.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->getAttr('username', $this->getId());
    }

    /**
     * Get the nickname / username for the user.
     *
     * @return string
     */
    public function getNickname(): ?string
    {
        return $this->getAttr('nickname');
    }

    /**
     * Get the full name of the user.
     *
     * @return string
     */
    public function getName(): ?string
    {
        return $this->getAttr('name');
    }

    /**
     * Get the e-mail address of the user.
     *
     * @return string
     */
    public function getEmail(): ?string
    {
        return $this->getAttr('email');
    }

    /**
     * Get the avatar / image URL for the user.
     *
     * @return string
     */
    public function getAvatar(): ?string
    {
        return $this->getAttr('avatar');
    }

    /**
     * Set the token on the user.
     *
     * @param string $token
     *
     * @return $this
     */
    public function setToken(string $token)
    {
        $this->setAttr('token', $token);
        return $this;
    }

    /**
     * Get the authorized token.
     *
     * @return \isszz\third\AccessToken
     */
    public function getToken(): ?string
    {
        return $this->getAttr('token');
        // return new AccessToken(['access_token' => $this->getAttr('token')]);
    }

    public function setRefreshToken(?string $refreshToken): self
    {
        $this->setAttr('refresh_token', $refreshToken);

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->getAttr('refresh_token');
    }

    public function setExpiresIn(int $expiresIn): self
    {
        return $this->setAttr('expires_in', $expiresIn);
    }

    public function getExpiresIn(): ?int
    {
        return $this->getAttr('expires_in');
    }

    public function setRaw(array $user): self
    {
        $this->setAttr('raw', $user);

        return $this;
    }

    /**
     * Get the raw attrs.
     *
     * @return array
     */
    public function getRaw(): array
    {
        return $this->getAttr('raw');
    }

    public function setTokenResponse(array $response)
    {
        $this->setAttr('token_response', $response);

        return $this;
    }

    public function getTokenResponse()
    {
        return $this->getAttr('token_response');
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize(): array
    {
        return $this->attrs;
    }

    public function serialize()
    {
        return serialize($this->attrs);
    }

    /**
     * Constructs the object.
     *
     * @param string $serialized The string representation of the object.
     */
    public function unserialize($serialized)
    {
        $this->attrs = \unserialize($serialized) ?: [];
    }

    /**
     * @return \isszz\third\interfaces\DriverInterface
     */
    public function getDriver(): \isszz\third\interfaces\DriverInterface
    {
        return $this->driver;
    }

    /**
     * @param \isszz\third\interfaces\DriverInterface $driver
     *
     * @return $this
     */
    public function setDriver(\isszz\third\interfaces\DriverInterface $driver)
    {
        $this->driver = $driver;

        return $this;
    }
    
}
