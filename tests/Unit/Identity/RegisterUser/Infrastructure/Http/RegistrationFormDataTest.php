<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RegisterUser\Infrastructure\Http;

use App\Identity\RegisterUser\Infrastructure\Http\RegistrationFormData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

#[CoversClass(RegistrationFormData::class)]
final class RegistrationFormDataTest extends TestCase
{
    public function testEveryFieldIsRequired(): void
    {
        self::assertSame(['name', 'email', 'password'], $this->invalidFields($this->validate(null, null, null)));
    }

    public function testABlankNameIsRejected(): void
    {
        self::assertSame(['name'], $this->invalidFields($this->validate('   ', 'john@example.com', 's3cret-Passw0rd')));
    }

    public function testTheNameCannotBeLongerThanTheDomainAllows(): void
    {
        self::assertSame(['name'], $this->invalidFields($this->validate(str_repeat('a', 101), 'john@example.com', 's3cret-Passw0rd')));
    }

    public function testTheEmailMustBeWellFormedAndFitTheDomainLimit(): void
    {
        self::assertSame(['email'], $this->invalidFields($this->validate('John Doe', 'not-an-email', 's3cret-Passw0rd')));
        self::assertSame(['email'], $this->invalidFields($this->validate('John Doe', str_repeat('a', 170).'@example.com', 's3cret-Passw0rd')));
    }

    public function testThePasswordNeedsAMinimumLength(): void
    {
        self::assertSame(['password'], $this->invalidFields($this->validate('John Doe', 'john@example.com', 'short')));
    }

    public function testThePasswordCannotExceedWhatTheHasherAccepts(): void
    {
        self::assertSame(['password'], $this->invalidFields($this->validate('John Doe', 'john@example.com', str_repeat('a', 4097))));
    }

    public function testValidDataPasses(): void
    {
        self::assertCount(0, $this->validate('John Doe', 'john@example.com', 's3cret-Passw0rd'));
    }

    private function validate(?string $name, ?string $email, ?string $password): ConstraintViolationListInterface
    {
        $data = new RegistrationFormData();
        $data->name = $name;
        $data->email = $email;
        $data->password = $password;

        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($data);
    }

    /**
     * @return list<string>
     */
    private function invalidFields(ConstraintViolationListInterface $violations): array
    {
        $fields = [];
        foreach ($violations as $violation) {
            $fields[] = $violation->getPropertyPath();
        }

        return array_values(array_unique($fields));
    }
}
