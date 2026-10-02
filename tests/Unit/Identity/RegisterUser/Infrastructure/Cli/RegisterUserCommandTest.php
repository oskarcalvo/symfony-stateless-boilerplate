<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RegisterUser\Infrastructure\Cli;

use App\Identity\Domain\Email;
use App\Identity\RegisterUser\Application\RegisterUser;
use App\Identity\RegisterUser\Infrastructure\Cli\RegisterUserCommand;
use App\Tests\Double\Identity\FakePasswordHasher;
use App\Tests\Double\Identity\InMemoryUserRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(RegisterUserCommand::class)]
final class RegisterUserCommandTest extends TestCase
{
    private InMemoryUserRepository $users;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->users = new InMemoryUserRepository();
        $application = new Application();
        $application->addCommand(new RegisterUserCommand(new RegisterUser($this->users, new FakePasswordHasher(), new MockClock())));
        $this->tester = new CommandTester($application->find('identity:user:register'));
    }

    public function testItRegistersTheUser(): void
    {
        $status = $this->tester->execute(['email' => 'john@example.com', 'password' => 's3cret', 'name' => 'John Doe']);

        self::assertSame(Command::SUCCESS, $status);
        self::assertStringContainsString('User "john@example.com" registered', $this->tester->getDisplay());
        self::assertSame('John Doe', $this->users->ofEmail(Email::fromString('john@example.com'))?->name()->value);
    }

    public function testADuplicatedEmailFails(): void
    {
        $this->tester->execute(['email' => 'john@example.com', 'password' => 's3cret', 'name' => 'John Doe']);

        $status = $this->tester->execute(['email' => 'john@example.com', 'password' => 'other', 'name' => 'Other']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('already exists', $this->tester->getDisplay());
    }

    public function testAnInvalidEmailFails(): void
    {
        $status = $this->tester->execute(['email' => 'not-an-email', 'password' => 's3cret', 'name' => 'John Doe']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('is not a valid email', $this->tester->getDisplay());
        self::assertSame(0, $this->users->saves);
    }

    public function testABlankNameFails(): void
    {
        $status = $this->tester->execute(['email' => 'john@example.com', 'password' => 's3cret', 'name' => '  ']);

        self::assertSame(Command::FAILURE, $status);
        self::assertStringContainsString('is not a valid name', $this->tester->getDisplay());
        self::assertSame(0, $this->users->saves);
    }
}
