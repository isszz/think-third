<?php

namespace isszz\third;

use think\Manager;

class Third extends Manager
{
    protected $namespace = '\\isszz\\third\\driver\\';

    public function app(string $name = null): Driver
    {
        return $this->driver($name);
    }

    protected function resolveType(string $name)
    {
        return $this->app->config->get("third.apps.{$name}.type") ?? $name;
    }

    protected function resolveConfig(string $name)
    {
        return $this->app->config->get("third.apps.{$name}", []);
        // return ($this->app->config->get("third.apps.{$name}", []) + ['redirect' => $this->app->config->get('third.redirect', '')]);
    }

    public function checkUser(User $user, $autoLogin = true): bool
    {
        $checker = $this->app->config->get('third.user_checker');

        if ($checker) {
            return $this->app->invoke($checker, [$user, $autoLogin]);
        }

        return false;
    }

    public function setUser(User $user)
    {
        $this->app->session->flash('third_user', $user);
    }

    public function getUser(): User
    {
        return $this->app->session->get('third_user');
    }

    public function getDefaultDriver()
    {
        return null;
    }
}