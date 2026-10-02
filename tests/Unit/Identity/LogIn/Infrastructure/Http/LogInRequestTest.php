<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\LogIn\Infrastructure\Http;

use App\Identity\LogIn\Infrastructure\Http\LogInRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

#[CoversClass(LogInRequest::class)]
final class LogInRequestTest extends TestCase
{
    public function testEmailAndPasswordAreRequired(): void
    {
        self::assertSame(['email', 'password'], $this->invalidFields(new LogInRequest()));
    }

    public function testTheEmailMustBeWellFormed(): void
    {
        self::assertSame(['email'], $this->invalidFields(new LogInRequest('not-an-email', 's3cret')));
    }

    public function testValidDataPasses(): void
    {
        self::assertSame([], $this->invalidFields(new LogInRequest('john@example.com', 's3cret')));
        self::assertTrue((new \ReflectionClass(LogInRequest::class))->isReadOnly());
    }

    /**
     * @return list<string>
     */
    private function invalidFields(LogInRequest $request): array
    {
        $fields = [];
        foreach (Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($request) as $violation) {
            $fields[] = $violation->getPropertyPath();
        }

        return array_values(array_unique($fields));
    }
}
