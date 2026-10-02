<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\RegisterUser\Infrastructure\Http;

use App\Identity\RegisterUser\Infrastructure\Http\RegisterUserRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

#[CoversClass(RegisterUserRequest::class)]
final class RegisterUserRequestTest extends TestCase
{
    public function testMissingFieldsAreReportedOneByOne(): void
    {
        self::assertSame(['name', 'email', 'password'], $this->invalidFields(new RegisterUserRequest()));
    }

    public function testItAppliesTheSameRulesAsTheWebForm(): void
    {
        self::assertSame(['name'], $this->invalidFields(new RegisterUserRequest('  ', 'john@example.com', 's3cret-Passw0rd')));
        self::assertSame(['email'], $this->invalidFields(new RegisterUserRequest('John Doe', 'not-an-email', 's3cret-Passw0rd')));
        self::assertSame(['password'], $this->invalidFields(new RegisterUserRequest('John Doe', 'john@example.com', 'short')));
    }

    public function testValidDataPasses(): void
    {
        self::assertSame([], $this->invalidFields(new RegisterUserRequest('John Doe', 'john@example.com', 's3cret-Passw0rd')));
        self::assertTrue((new \ReflectionClass(RegisterUserRequest::class))->isReadOnly());
    }

    /**
     * @return list<string>
     */
    private function invalidFields(RegisterUserRequest $request): array
    {
        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($request);
        \assert($violations instanceof ConstraintViolationListInterface);

        $fields = [];
        foreach ($violations as $violation) {
            $fields[] = $violation->getPropertyPath();
        }

        return array_values(array_unique($fields));
    }
}
