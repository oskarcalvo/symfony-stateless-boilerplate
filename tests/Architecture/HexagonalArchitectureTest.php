<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * Railgun: dependencies point inwards. Infrastructure → Application → Domain.
 */
final class HexagonalArchitectureTest extends TestCase
{
    /**
     * The only third-party code the inner layers may touch: value-like libraries with no I/O.
     */
    private const array INNER_LAYER_ALLOWED_VENDORS = [
        'Symfony\\Component\\Uid\\',
        'Psr\\Clock\\',
    ];

    public function testDomainDependsOnlyOnTheDomain(): void
    {
        $violations = [];
        foreach ($this->filesInLayer('Domain') as $file) {
            foreach (SourceCode::dependencies($file) as $dependency) {
                if (!$this->isDomain($dependency) && !$this->isAllowedVendor($dependency)) {
                    $violations[] = \sprintf('%s → %s', $file['relativePath'], $dependency);
                }
            }
        }

        self::assertSame([], $violations, 'Domain must not depend on Application, Infrastructure or frameworks.');
    }

    public function testApplicationDependsOnlyOnApplicationAndDomain(): void
    {
        $violations = [];
        foreach ($this->filesInLayer('Application') as $file) {
            foreach (SourceCode::dependencies($file) as $dependency) {
                $isApplication = str_starts_with($dependency, 'App\\') && str_contains($dependency, '\\Application\\');
                if (!$isApplication && !$this->isDomain($dependency) && !$this->isAllowedVendor($dependency)) {
                    $violations[] = \sprintf('%s → %s', $file['relativePath'], $dependency);
                }
            }
        }

        self::assertSame([], $violations, 'Application must only use Domain (ports) and its own slice; adapters live in Infrastructure.');
    }

    public function testEveryClassBelongsToALayer(): void
    {
        $orphans = [];
        foreach (SourceCode::files() as $file) {
            if ('Kernel.php' !== $file['relativePath'] && null === SourceCode::layerOf($file['relativePath'])) {
                $orphans[] = $file['relativePath'];
            }
        }

        self::assertSame([], $orphans, 'Every file must live under a Domain, Application or Infrastructure directory.');
    }

    public function testControllersAreHttpAdapters(): void
    {
        $violations = [];
        foreach (SourceCode::files() as $file) {
            if (SourceCode::isController($file) && !str_contains($file['relativePath'], '/Infrastructure/Http/')) {
                $violations[] = $file['relativePath'];
            }
        }

        self::assertSame([], $violations, 'Controllers belong to <Slice>/Infrastructure/Http.');
    }

    /**
     * @return iterable<array{relativePath: string, imports: list<string>, code: string}>
     */
    private function filesInLayer(string $layer): iterable
    {
        foreach (SourceCode::files() as $file) {
            if ($layer === SourceCode::layerOf($file['relativePath'])) {
                yield $file;
            }
        }
    }

    private function isDomain(string $class): bool
    {
        return str_starts_with($class, 'App\\') && str_contains($class, '\\Domain\\');
    }

    private function isAllowedVendor(string $class): bool
    {
        foreach (self::INNER_LAYER_ALLOWED_VENDORS as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
