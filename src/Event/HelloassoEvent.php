<?php

/*
 * This file is part of the FOSOAuthServerBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Event;

use App\Entity\HelloassoPayment;
use App\Entity\User;

class HelloassoEvent extends \Symfony\Contracts\EventDispatcher\Event
{
    public const PAYMENT_AFTER_SAVE = 'helloasso.payment_after_save';
    public const ORPHAN_SOLVE = 'helloasso.orphan_solve';
    public const RE_REGISTRATION_SUCCESS = 'helloasso.registration_success';
    public const TOO_EARLY = 'helloasso.too_early';

    /**
     * @var HelloassoPayment
     */
    private $payment;

    /**
     * @var User
     */
    private $user;

    public function __construct(HelloassoPayment $payment, ?User $user = null)
    {
        $this->payment = $payment;
        $this->user = $user;
    }

    /**
     * @return HelloassoPayment
     */
    public function getPayment()
    {
        return $this->payment;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }
}
