<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use Symfony\Component\Finder\Finder;

/**
 * Minimal static view of src/: one entry per PHP file with its namespace, imports and declared type.
 */
final class SourceCode
{
    public const string SRC = __DIR__.'/../../src';

    /**
     * @return array<string, array{path: string, relativePath: string, namespace: string, imports: list<string>, code: string, kind: ?string, name: ?string}>
     */
    public static function files(): array
    {
        $files = [];
        foreach ((new Finder())->files()->in(self::SRC)->name('*.php')->sortByName() as $file) {
            $code = $file->getContents();
            preg_match('/^namespace\s+([^;]+);/m', $code, $namespace);
            preg_match_all('/^use\s+(?:function\s+|const\s+)?([^;\s]+)(?:\s+as\s+\w+)?;/m', $code, $imports);
            preg_match('/^(?:(?:final|abstract|readonly)\s+)*(class|interface|trait|enum)\s+(\w+)/m', $code, $declaration);

            $relativePath = str_replace('\\', '/', $file->getRelativePathname());
            $files[$relativePath] = [
                'path' => $file->getRealPath(),
                'relativePath' => $relativePath,
                'namespace' => $namespace[1] ?? '',
                'imports' => $imports[1],
                'code' => $code,
                'kind' => $declaration[1] ?? null,
                'name' => $declaration[2] ?? null,
            ];
        }

        return $files;
    }

    /**
     * Every class/type referenced by the file, through "use" or written fully qualified in the code.
     *
     * @param array{imports: list<string>, code: string} $file
     *
     * @return list<string>
     */
    public static function dependencies(array $file): array
    {
        preg_match_all('/(?<![\w\\\\])\\\\?((?:App|Symfony|Doctrine|Lexik|Twig|Psr)\\\\[\w\\\\]+)/', preg_replace('/^(namespace|use)\s.*$/m', '', $file['code']), $inline);

        return array_values(array_unique([...$file['imports'], ...$inline[1]]));
    }

    /**
     * @param array{code: string} $file
     */
    public static function isController(array $file): bool
    {
        return 1 === preg_match('/\bextends\s+\\\\?(?:[\w\\\\]+\\\\)?AbstractController\b/', $file['code']);
    }

    /**
     * src/<Context>/... → "Context".
     */
    public static function contextOf(string $relativePath): ?string
    {
        $parts = explode('/', $relativePath);

        return \count($parts) > 1 ? $parts[0] : null;
    }

    /**
     * src/<Context>/<Slice>/... → "Slice", or null for the context-wide Domain/Application/Infrastructure.
     */
    public static function sliceOf(string $relativePath): ?string
    {
        $parts = explode('/', $relativePath);
        if (\count($parts) < 3 || \in_array($parts[1], ['Domain', 'Application', 'Infrastructure'], true)) {
            return null;
        }

        return $parts[1];
    }

    /**
     * The hexagonal layer: Domain, Application or Infrastructure.
     */
    public static function layerOf(string $relativePath): ?string
    {
        foreach (\array_slice(explode('/', $relativePath), 1, -1) as $part) {
            if (\in_array($part, ['Domain', 'Application', 'Infrastructure'], true)) {
                return $part;
            }
        }

        return null;
    }
}
