<?php

declare(strict_types=1);

namespace App\Identity\Domain;

/**
 * The name the user is shown by. Surrounding and repeated inner whitespace is collapsed.
 */
final readonly class UserName implements \Stringable
{
    public const int MAX_LENGTH = 100;

    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $value));

        if ('' === $normalized || mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a valid name: it must have between 1 and %d characters.', $value, self::MAX_LENGTH));
        }

        return new self($normalized);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
