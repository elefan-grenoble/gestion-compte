<?php

/*
 * This file is part of the FOSOAuthServerBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace AppBundle\Event;

use AppBundle\Entity\HelloassoPayment;
use Symfony\Component\EventDispatcher\Event;

class HelloassoEvent extends Event
{
    const PAYMENT_AFTER_SAVE = 'helloasso.payment_after_save';

    /**
     * @var HelloassoPayment
     */
    private $payment;


    /**
     * @param HelloassoPayment $payment
     */
    public function __construct(HelloassoPayment $payment)
    {
        $this->payment = $payment;
    }

    /**
     * @return HelloassoPayment
     */
    public function getPayment()
    {
        return $this->payment;
    }

}
