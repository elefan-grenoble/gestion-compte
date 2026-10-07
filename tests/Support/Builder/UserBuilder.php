<?php

namespace App\Tests\Support\Builder;

use App\Entity\User;

/**
 * Builds an enabled User. The plain password is hashed by FOSUserBundle's
 * Doctrine listener when the user is persisted.
 *
 * The user has logged in before (lastLogin is now), as in the fixtures: some
 * pages assume it, and a session set up by FunctionalTestCase::logIn() does
 * not go through the login that would set it.
 *
 *     $admin = UserBuilder::aUser()->withRoles('ROLE_ADMIN')->build();
 */
final class UserBuilder
{
    /** @var string */
    private $username;

    /** @var null|string */
    private $email;

    /** @var string */
    private $password = 'password';

    /** @var string[] */
    private $roles = [];

    /** @var bool */
    private $enabled = true;

    private function __construct()
    {
        $this->username = 'test-user-' . UniqueSequence::next();
    }

    public static function aUser(): self
    {
        return new self();
    }

    public function withUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function withEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function withPassword(string $plainPassword): self
    {
        $this->password = $plainPassword;

        return $this;
    }

    public function withRoles(string ...$roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function disabled(): self
    {
        $this->enabled = false;

        return $this;
    }

    public function build(): User
    {
        $user = new User();
        $user->setUsername($this->username);
        $user->setEmail($this->email ?? $this->username . '@test.local');
        $user->setPlainPassword($this->password);
        $user->setEnabled($this->enabled);
        $user->setRoles($this->roles);
        $user->setLastLogin(new \DateTime());

        return $user;
    }
}
