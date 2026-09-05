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
 * The parser is not a dependency of this bundle - there is no build here to
 * hang one off. It is borrowed from wherever one happens to be installed,
 * which in practice is the Sulu application next door, the one that compiles
 * this admin in the first place. When there is none the test skips rather than
 * fails: a contributor without a JavaScript toolchain should not see a red
 * suite over a check they cannot run.
 */
final class JsSyntaxTest extends TestCase
{
    /**
     * Where a @babel/parser may be found, nearest first.
     *
     * @var list<string>
     */
    private const PARSER_PATHS = [
        'public/js/node_modules/@babel/parser',
        'node_modules/@babel/parser',
        '../sulu-base/node_modules/@babel/parser',
        '../../sulu-base/node_modules/@babel/parser',
    ];

    #[Test]
    public function everyAdminScriptParses(): void
    {
        $parser = self::locateParser();
        if (null === $parser) {
            self::markTestSkipped(
                'No @babel/parser found. This check borrows one from a Sulu application next to '
                . 'the bundle, since there is no JavaScript build here to depend on.',
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
