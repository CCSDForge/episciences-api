<?php

namespace App\Entity;

use Symfony\Component\Security\Core\User\UserInterface;

interface UserOwnedInterface
{
    public function getUser(): ?UserInterface;

    public function setUser(UserInterface $user): self;



}