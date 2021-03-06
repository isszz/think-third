<?php

namespace isszz\third\interfaces;

interface UserInterface
{
    public function getId();

    public function getNickname(): ?string;

    public function getName(): ?string;

    public function getEmail(): ?string;
    
    public function getAvatar(): ?string;

    public function getToken(): ?string;
    // public function getAccessToken(): ?string;

    public function getRefreshToken(): ?string;

    public function getExpiresIn(): ?int;
}
