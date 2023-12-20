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
use Symfony\Component\EventDispatcher\Event;

class HelloassoEvent extends Event
{
    const PAYMENT_AFTER_SAVE = 'helloasso.payment_after_save';
    const ORPHAN_SOLVE = 'helloasso.orphan_solve';
    const RE_REGISTRATION_SUCCESS = 'helloasso.registration_success';
    const TOO_EARLY = 'helloasso.too_early';

    /**
     * @var HelloassoPayment
     */
    private $payment;
    /**
     * @var User
     */
    private $user;


    /**
     * @param HelloassoPayment $payment
     * @param User $user
     */
    public function __construct(HelloassoPayment $payment,User $user = null)
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
