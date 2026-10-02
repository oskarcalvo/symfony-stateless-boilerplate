<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\GetAuthenticatedUser\Application;

use App\Identity\Domain\UserId;
use App\Identity\Domain\UserNotFound;
use App\Identity\GetAuthenticatedUser\Application\GetAuthenticatedUser;
use App\Tests\Double\Identity\InMemoryUserRepository;
use App\Tests\Double\Identity\UserMother;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetAuthenticatedUser::class)]
final class GetAuthenticatedUserTest extends TestCase
{
    public function testItReturnsAViewOfTheUser(): void
    {
        $users = new InMemoryUserRepository();
        $user = UserMother::create('john@example.com', name: 'John Doe');
        $users->save($user);

        $view = (new GetAuthenticatedUser($users))($user->id());

        self::assertSame($user->id()->value, $view->id);
        self::assertSame('john@example.com', $view->email);
        self::assertSame('John Doe', $view->name);
        self::assertSame(['ROLE_USER'], $view->roles);
        self::assertSame($user->registeredAt(), $view->registeredAt);
    }

    public function testItFailsWhenTheUserNoLongerExists(): void
    {
        $this->expectException(UserNotFound::class);

        (new GetAuthenticatedUser(new InMemoryUserRepository()))(UserId::generate());
    }
}
