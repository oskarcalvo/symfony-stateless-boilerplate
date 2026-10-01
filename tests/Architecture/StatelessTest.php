<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Railgun: no server-side state about the user. Identity lives in the JWT, data in the database.
 */
final class StatelessTest extends KernelTestCase
{
    private const string CONFIG = __DIR__.'/../../config/packages';

    public function testSessionsAreDisabled(): void
    {
        self::assertFalse(Yaml::parseFile(self::CONFIG.'/framework.yaml')['framework']['session'] ?? null);
        self::assertFalse(static::getContainer()->has('session.factory'));
    }

    public function testEveryFirewallIsStateless(): void
    {
        $firewalls = Yaml::parseFile(self::CONFIG.'/security.yaml')['security']['firewalls'];

        foreach ($firewalls as $name => $firewall) {
            self::assertTrue(
                false === ($firewall['security'] ?? true) || true === ($firewall['stateless'] ?? false),
                \sprintf('Firewall "%s" must be "stateless: true" (or "security: false").', $name),
            );
        }
    }

    public function testNoCodeUsesTheSession(): void
    {
        $violations = [];
        foreach (SourceCode::files() as $file) {
            if (preg_match('/getSession\(|SessionInterface|\$_SESSION|session_start|->getFlashBag|addFlash\(/', $file['code'])) {
                $violations[] = $file['relativePath'];
            }
        }

        self::assertSame([], $violations, 'Keep state in the JWT (identity) or the database (data), never in a session.');
    }
}
