<?php

namespace App\Tests\Unit\Providers;

use App\Providers\Helloasso\HelloassoNotificationRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class HelloassoNotificationRequestTest extends TestCase
{
    public function testReadsTheEventTypeAndTheData(): void
    {
        $notification = HelloassoNotificationRequest::createFromRequest($this->aRequest('{"eventType": "Payment", "data": {"id": 12, "state": "Authorized"}}'));

        $this->assertSame('Payment', $notification->eventType);
        $this->assertSame(['id' => 12, 'state' => 'Authorized'], $notification->data);
        $this->assertTrue($notification->isPaymentValidated());
    }

    /**
     * A body that is not a JSON object is rejected as such (I-BUG-8: it
     * was reported as a missing eventType).
     *
     * @dataProvider notAJsonObjectProvider
     */
    public function testABodyThatIsNotAJsonObjectIsRejected(string $body): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('helloasso notification is not a JSON object');

        HelloassoNotificationRequest::createFromRequest($this->aRequest($body));
    }

    public function notAJsonObjectProvider(): array
    {
        return ['empty' => [''], 'not JSON' => ['eventType=Payment'], 'a JSON string' => ['"Payment"']];
    }

    public function testANotificationWithoutEventTypeIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot find eventType in helloasso notification');

        HelloassoNotificationRequest::createFromRequest($this->aRequest('{"data": {}}'));
    }

    public function testANotificationWithoutDataIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot find data in helloasso notification');

        HelloassoNotificationRequest::createFromRequest($this->aRequest('{"eventType": "Payment"}'));
    }

    public function testAPaymentWithoutStateIsNotValidated(): void
    {
        $this->assertFalse((new HelloassoNotificationRequest(['id' => 12], 'Payment'))->isPaymentValidated());
    }

    private function aRequest(string $body): Request
    {
        return new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/json'], $body);
    }
}
