<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Service;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards that every Doctrine repository is declared under its own class name.
 *
 * Doctrine resolves a repository through a service locator keyed by the CLASS
 * name, and an alias does not enter a locator. A repository declared under a
 * name of our own with the class as an alias is therefore tagged, visible in
 * `debug:container`, and still unreachable: `getRepository(Entity::class)`
 * throws "its service could not be found", pointing at the interface rather
 * than at the id.
 *
 * It cost a released command that could not start, and nothing in the bundle
 * caught it: every other call site injects the repository explicitly, which
 * works either way round. So the contract is on the declaration, not on the
 * call.
 *
 * The bundle's `itech_world.sulu_tailwind_theme.*` convention is kept as the
 * alias, so both names resolve and no project has to change.
 */
final class RepositoryServiceIdContractTest extends TestCase
{
    /**
     * The service definitions and aliases of the bundle.
     *
     * @return array<string, mixed>
     */
    private static function services(): array
    {
        $file = \dirname(__DIR__, 2) . '/config/services.yaml';
        $parsed = Yaml::parseFile($file);

        self::assertIsArray($parsed);
        self::assertArrayHasKey('services', $parsed, 'services.yaml holds no services.');
        self::assertIsArray($parsed['services']);

        return $parsed['services'];
    }

    /**
     * Every repository class is a service id, never only an alias.
     */
    #[Test]
    public function everyRepositoryIsDeclaredUnderItsClassName(): void
    {
        $services = self::services();

        // Only the Doctrine ones. The directory also holds a plain service that
        // queries the database without being an entity repository, and nothing
        // in the locator concerns it.
        $classes = [];
        foreach (glob(\dirname(__DIR__, 2) . '/src/Repository/*.php') ?: [] as $path) {
            if (!str_contains((string) file_get_contents($path), 'extends ServiceEntityRepository')) {
                continue;
            }

            $classes[] = 'ItechWorld\\SuluTailwindThemeBundle\\Repository\\' . basename($path, '.php');
        }

        self::assertNotEmpty($classes, 'No Doctrine repository was found.');

        $offenders = [];
        foreach ($classes as $class) {
            $definition = $services[$class] ?? null;

            if (null === $definition) {
                $offenders[] = \sprintf('%s is not declared as a service id at all', $class);
                continue;
            }

            // A string value is an alias: `Class: '@some.other.id'`. That is the
            // shape that looks right and does not work.
            if (\is_string($definition)) {
                $offenders[] = \sprintf(
                    '%s is only an alias to %s, which never enters the repository locator',
                    $class,
                    $definition,
                );
            }
        }

        self::assertSame(
            [],
            $offenders,
            "A repository Doctrine cannot resolve is a call to getRepository() that throws at\n"
            . "runtime, with a message naming the interface rather than the id:\n  "
            . implode("\n  ", $offenders),
        );
    }

    /**
     * The house naming convention still resolves, as an alias.
     *
     * Inverting the two must not make a project rewrite its own service
     * arguments, and the bundle itself refers to these ids in a dozen places.
     */
    #[Test]
    public function theHouseIdsStillResolve(): void
    {
        $services = self::services();

        foreach (['itech_world.sulu_tailwind_theme.repository',
            'itech_world.sulu_tailwind_theme.webspace_theme_repository'] as $id) {
            self::assertArrayHasKey(
                $id,
                $services,
                \sprintf('%s disappeared, which breaks every project injecting it.', $id),
            );
        }
    }
}
