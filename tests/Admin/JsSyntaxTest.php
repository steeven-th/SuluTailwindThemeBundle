<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Parses every admin JavaScript file the bundle ships.
 *
 * These components are compiled by the host application and never here, so a
 * syntax error in this repository is not caught by anything: it surfaces as a
 * broken admin build in someone else's project, after a release. A stray
 * character in a comment is enough.
 *
 * The parser is installed into the bundle, not depended on through the admin
 * package: that one declares peer dependencies on React and sulu-admin-bundle,
 * which npm would try to resolve for a check that needs neither. A bare
 * `npm install --no-save @babel/parser` at the root sidesteps all of it.
 *
 * With no parser installed the test skips rather than fails, so a contributor
 * without a JavaScript toolchain does not face a red suite over a check they
 * cannot run. CI installs it, which is where the guarantee actually matters.
 */
final class JsSyntaxTest extends TestCase
{
    /**
     * Where a @babel/parser may be found, nearest first.
     *
     * Both inside the bundle. An earlier version also looked in a Sulu
     * application beside it, which worked on the machine it was written on and
     * nowhere else - a contributor has no reason to keep one there, and CI
     * certainly does not.
     *
     * @var list<string>
     */
    private const PARSER_PATHS = [
        'node_modules/@babel/parser',
        'public/js/node_modules/@babel/parser',
    ];

    #[Test]
    public function everyAdminScriptParses(): void
    {
        $parser = self::locateParser();
        if (null === $parser) {
            self::markTestSkipped(
                'No @babel/parser installed. Run `npm install --no-save @babel/parser` at the '
                . 'root of the bundle to run this check locally. CI installs it.',
            );
        }

        $scripts = self::scripts();
        self::assertGreaterThan(10, \count($scripts), 'The admin scripts were not found.');

        $command = \sprintf(
            'node %s %s %s 2>&1',
            escapeshellarg(self::root() . '/tests/js-syntax-check.js'),
            escapeshellarg($parser),
            implode(' ', array_map('escapeshellarg', $scripts)),
        );

        $output = [];
        $status = 0;
        exec($command, $output, $status);

        self::assertSame(
            0,
            $status,
            "Admin JavaScript failed to parse. The host application compiles these files, so this\n"
            . "would have shown up as a broken admin build in a project using the bundle:\n  "
            . implode("\n  ", $output),
        );
    }

    /**
     * The parser to hand to the checker, or null when none is installed.
     */
    private static function locateParser(): ?string
    {
        foreach (self::PARSER_PATHS as $candidate) {
            $path = self::root() . '/' . $candidate;
            if (is_dir($path)) {
                return (string) realpath($path);
            }
        }

        return null;
    }

    /**
     * Every JavaScript file under public/js, node_modules excluded.
     *
     * @return list<string>
     */
    private static function scripts(): array
    {
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::root() . '/public/js'),
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || 'js' !== $file->getExtension()) {
                continue;
            }

            $path = (string) $file->getPathname();
            if (!str_contains($path, '/node_modules/')) {
                $found[] = $path;
            }
        }

        sort($found);

        return $found;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 2);
    }
}
