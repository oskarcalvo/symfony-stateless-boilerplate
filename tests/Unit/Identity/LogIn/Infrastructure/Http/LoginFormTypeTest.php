<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\LogIn\Infrastructure\Http;

use App\Identity\LogIn\Infrastructure\Http\LoginFormData;
use App\Identity\LogIn\Infrastructure\Http\LoginFormType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[CoversClass(LoginFormType::class)]
// Symfony's TypeTestCase creates an EventDispatcher mock it never sets expectations on.
#[AllowMockObjectsWithoutExpectations]
final class LoginFormTypeTest extends TypeTestCase
{
    private CsrfTokenManagerInterface $csrfTokenManager;

    protected function setUp(): void
    {
        $this->csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $this->csrfTokenManager->method('getToken')->willReturnCallback(static fn (string $id) => new CsrfToken($id, 'csrf-token'));
        $this->csrfTokenManager->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token) => 'authenticate' === $token->getId() && 'csrf-token' === $token->getValue());

        parent::setUp();
    }

    protected function getExtensions(): array
    {
        return [new CsrfExtension($this->csrfTokenManager)];
    }

    public function testItHasAnEmailAndAPasswordField(): void
    {
        $form = $this->factory->create(LoginFormType::class);

        self::assertInstanceOf(EmailType::class, $form->get('email')->getConfig()->getType()->getInnerType());
        self::assertInstanceOf(PasswordType::class, $form->get('password')->getConfig()->getType()->getInnerType());
    }

    public function testSubmittedValuesAreMappedToTheDto(): void
    {
        $form = $this->factory->create(LoginFormType::class);

        $form->submit(['email' => 'john@example.com', 'password' => 's3cret', '_token' => 'csrf-token']);

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        $data = $form->getData();
        self::assertInstanceOf(LoginFormData::class, $data);
        self::assertSame('john@example.com', $data->email);
        self::assertSame('s3cret', $data->password);
    }

    public function testItIsProtectedWithTheStatelessAuthenticateCsrfToken(): void
    {
        $form = $this->factory->create(LoginFormType::class);

        self::assertSame('authenticate', $form->getConfig()->getOption('csrf_token_id'));
        self::assertTrue($form->getConfig()->getOption('csrf_protection'));

        $form->submit(['email' => 'john@example.com', 'password' => 's3cret', '_token' => 'forged']);

        self::assertFalse($form->isValid());
    }
}
