<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Railgun: src/ screams the business. Top-level directories are bounded contexts, and contexts
 * only talk to each other through App\Shared.
 */
final class ScreamingArchitectureTest extends TestCase
{
    private const string TECHNICAL_NAMES = '/^(Controller|Entity|Repository|Service|Form|Command|EventListener|EventSubscriber|Security|Model|Util|Helper|Manager|Domain|Application|Infrastructure|Http|Api)s?$/';

    public function testTopLevelDirectoriesAreBoundedContexts(): void
    {
        $technical = [];
        foreach ((new Finder())->directories()->in(SourceCode::SRC)->depth(0) as $directory) {
            if (preg_match(self::TECHNICAL_NAMES, $directory->getFilename())) {
                $technical[] = $directory->getFilename();
            }
        }

        self::assertSame([], $technical, 'src/ must contain business contexts (Identity, Billing...), not technical layers.');
    }

    public function testContextsDoNotDependOnEachOther(): void
    {
        $violations = [];
        foreach (SourceCode::files() as $file) {
            $context = SourceCode::contextOf($file['relativePath']);
            foreach (SourceCode::dependencies($file) as $dependency) {
                $parts = explode('\\', $dependency);
                if ('App' === $parts[0] && isset($parts[2]) && 'Shared' !== $parts[1] && $parts[1] !== $context) {
                    $violations[] = \sprintf('%s → %s', $file['relativePath'], $dependency);
                }
            }
        }

        self::assertSame([], $violations, 'Cross-context code goes through App\Shared (or an event), never a direct import.');
    }
}
