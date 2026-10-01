<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Security;

use App\Identity\Infrastructure\Security\WebLoginEntryPoint;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[CoversClass(WebLoginEntryPoint::class)]
final class WebLoginEntryPointTest extends TestCase
{
    public function testAnonymousVisitorsAreRedirectedToTheLoginForm(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnMap([['identity_login', [], UrlGeneratorInterface::ABSOLUTE_PATH, '/login']]);

        $response = (new WebLoginEntryPoint($urlGenerator))->start(Request::create('/private'));

        self::assertSame('/login', $response->getTargetUrl());
    }
}
