<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Railgun: a feature (slice) is self-contained. It may use its context's shared Domain and
 * Infrastructure, never another slice. Shared code never depends on a slice.
 */
final class VerticalSliceTest extends TestCase
{
    public function testSlicesDoNotDependOnOtherSlices(): void
    {
        $violations = [];
        foreach (SourceCode::files() as $file) {
            $context = SourceCode::contextOf($file['relativePath']);
            $slice = SourceCode::sliceOf($file['relativePath']);

            foreach (SourceCode::dependencies($file) as $dependency) {
                $target = $this->sliceOfClass($dependency, $context);
                if (null !== $target && $target !== $slice) {
                    $violations[] = \sprintf('%s → %s', $file['relativePath'], $dependency);
                }
            }
        }

        self::assertSame([], $violations, 'Move the shared piece to the context Domain/Infrastructure instead of importing another slice.');
    }

    public function testSlicesAreNamedAfterUseCases(): void
    {
        $violations = [];
        foreach (SourceCode::files() as $file) {
            $slice = SourceCode::sliceOf($file['relativePath']);
            if (null !== $slice && preg_match('/^(Controller|Entity|Repository|Service|Form|Command|Model|Util|Helper|Manager|Security|Shared|Common)s?$/', $slice)) {
                $violations[] = $file['relativePath'];
            }
        }

        self::assertSame([], $violations, 'A slice is a use case ("LogIn", "RegisterUser"), not a technical bucket.');
    }

    private function sliceOfClass(string $class, ?string $context): ?string
    {
        $parts = explode('\\', $class);
        if ('App' !== $parts[0] || \count($parts) < 4 || $parts[1] !== $context) {
            return null;
        }

        return \in_array($parts[2], ['Domain', 'Application', 'Infrastructure'], true) ? null : $parts[2];
    }
}
