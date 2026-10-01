<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\LogIn\Infrastructure\Http;

use App\Identity\LogIn\Infrastructure\Http\LoginFormData;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

#[CoversClass(LoginFormData::class)]
final class LoginFormDataTest extends TestCase
{
    public function testEmailAndPasswordAreRequired(): void
    {
        self::assertSame(['email', 'password'], $this->invalidFields($this->validate(null, null)));
    }

    public function testTheEmailMustBeWellFormed(): void
    {
        self::assertSame(['email'], $this->invalidFields($this->validate('not-an-email', 's3cret')));
    }

    public function testValidDataPasses(): void
    {
        self::assertCount(0, $this->validate('john@example.com', 's3cret'));
    }

    private function validate(?string $email, ?string $password): ConstraintViolationListInterface
    {
        $data = new LoginFormData();
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
