<?php

namespace isszz\third\interfaces;

use isszz\third\User;

interface DriverInterface
{
    /**
     * Redirect the user to the authentication page for the driver.
     *
     * @return \think\Request
     */
    public function redirect(?string $redirectUrl = null);

    /**
     * Get the User instance for the authenticated user.
     *
     * @param string $code
     *
     * @return \isszz\third\User
     */
    public function user(?string $code = null): User;

    public function buildUserByToken(string $token): User;

    public function formData(array $config = []): array;
}
