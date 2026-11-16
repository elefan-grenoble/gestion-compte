<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\AuthenticationSuccessHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @internal
 */
class AuthenticationSuccessHandlerTest extends TestCase
{
    public function testRedirectsToTheTargetPath(): void
    {
        $response = (new AuthenticationSuccessHandler())->onAuthenticationSuccess(new Request([], ['target_path' => '/booking/']), $this->createMock(TokenInterface::class));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/booking/', $response->getTargetUrl());
    }

    /**
     * The interface requires a Response (I-BUG-6: null without target path).
     */
    public function testRedirectsToTheHomepageWithoutTargetPath(): void
    {
        $response = (new AuthenticationSuccessHandler())->onAuthenticationSuccess(new Request(), $this->createMock(TokenInterface::class));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/', $response->getTargetUrl());
    }
}
