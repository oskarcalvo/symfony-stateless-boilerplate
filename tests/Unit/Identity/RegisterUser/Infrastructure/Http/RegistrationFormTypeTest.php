<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RegisterUser\Infrastructure\Http;

use App\Identity\RegisterUser\Infrastructure\Http\RegistrationFormData;
use App\Identity\RegisterUser\Infrastructure\Http\RegistrationFormType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[CoversClass(RegistrationFormType::class)]
// Symfony's TypeTestCase creates an EventDispatcher mock it never sets expectations on.
#[AllowMockObjectsWithoutExpectations]
final class RegistrationFormTypeTest extends TypeTestCase
{
    private CsrfTokenManagerInterface $csrfTokenManager;

    protected function setUp(): void
    {
        $this->csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $this->csrfTokenManager->method('getToken')->willReturnCallback(static fn (string $id) => new CsrfToken($id, 'csrf-token'));
        $this->csrfTokenManager->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token) => 'submit' === $token->getId() && 'csrf-token' === $token->getValue());

        parent::setUp();
    }

    protected function getExtensions(): array
    {
        return [new CsrfExtension($this->csrfTokenManager)];
    }

    public function testItHasANameAnEmailAndARepeatedPasswordField(): void
    {
        $form = $this->factory->create(RegistrationFormType::class);

        self::assertInstanceOf(TextType::class, $form->get('name')->getConfig()->getType()->getInnerType());
        self::assertInstanceOf(EmailType::class, $form->get('email')->getConfig()->getType()->getInnerType());
        self::assertInstanceOf(RepeatedType::class, $form->get('password')->getConfig()->getType()->getInnerType());
        self::assertInstanceOf(PasswordType::class, $form->get('password')->get('first')->getConfig()->getType()->getInnerType());
    }

    public function testSubmittedValuesAreMappedToTheDto(): void
    {
        $form = $this->factory->create(RegistrationFormType::class);

        $form->submit($this->submission());

        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        $data = $form->getData();
        self::assertInstanceOf(RegistrationFormData::class, $data);
        self::assertSame('John Doe', $data->name);
        self::assertSame('john@example.com', $data->email);
        self::assertSame('s3cret-Passw0rd', $data->password);
    }

    public function testBothPasswordsMustMatch(): void
    {
        $form = $this->factory->create(RegistrationFormType::class);

        $form->submit($this->submission(['password' => ['first' => 's3cret-Passw0rd', 'second' => 'other-Passw0rd']]));

        // The validator extension turns this into the "invalid_message" error (see the functional test).
        self::assertFalse($form->get('password')->isSynchronized());
        self::assertNull($form->getData()->password);
        self::assertSame('Las contraseñas no coinciden.', $form->get('password')->getConfig()->getOption('invalid_message'));
    }

    public function testItIsProtectedWithTheStatelessSubmitCsrfToken(): void
    {
        $form = $this->factory->create(RegistrationFormType::class);

        self::assertSame('submit', $form->getConfig()->getOption('csrf_token_id'));
        self::assertTrue($form->getConfig()->getOption('csrf_protection'));

        $form->submit($this->submission(['_token' => 'forged']));

        self::assertFalse($form->isValid());
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function submission(array $overrides = []): array
    {
        return [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => ['first' => 's3cret-Passw0rd', 'second' => 's3cret-Passw0rd'],
            '_token' => 'csrf-token',
            ...$overrides,
        ];
    }
}
