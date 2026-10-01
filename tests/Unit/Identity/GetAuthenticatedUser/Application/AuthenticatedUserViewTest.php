<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\GetAuthenticatedUser\Application;

use App\Identity\GetAuthenticatedUser\Application\AuthenticatedUserView;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AuthenticatedUserView::class)]
final class AuthenticatedUserViewTest extends TestCase
{
    public function testItIsAReadOnlySnapshotWithPublicFields(): void
    {
        $registeredAt = new \DateTimeImmutable('2026-01-01');

        $view = new AuthenticatedUserView('id', 'john@example.com', ['ROLE_USER'], $registeredAt);

        self::assertSame('id', $view->id);
        self::assertSame('john@example.com', $view->email);
        self::assertSame(['ROLE_USER'], $view->roles);
        self::assertSame($registeredAt, $view->registeredAt);
        self::assertTrue((new \ReflectionClass($view))->isReadOnly());
    }
}
