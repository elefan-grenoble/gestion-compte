<?php

declare(strict_types=1);

namespace AppBundle\Helloasso;

use Symfony\Component\HttpFoundation\Request;

class HelloassoNotificationRequest
{
    /** @var array */
    public $data;

    /** @var string */
    public $eventType;

    public function __construct(array $data, string $eventType)
    {
        $this->data = $data;
        $this->eventType = $eventType;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public static function createFromRequest(Request $request): self
    {
        $requestData = json_decode($request->getContent(), true);
        $eventType = $requestData['eventType'];
        if (!is_string($eventType)) {
            throw new \InvalidArgumentException('cannot find eventType in helloasso notification');
        }

        $data = $requestData['data'];
        if (!is_array($data)) {
            throw new \InvalidArgumentException('cannot find data in helloasso notification');
        }

        return new self($data, $eventType);
    }

    public function isPaymentValidated(): bool
    {
        return $this->eventType === 'Payment' && $this->data['state'] === 'Authorized';
    }
}