<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Templates;

use ItechWorld\SuluTailwindThemeBundle\Article\EventDateScope;
use ItechWorld\SuluTailwindThemeBundle\Article\EventSmartContentProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards every selection built on the event provider, wherever it is declared.
 *
 * A selection is bound to its provider in the template, and that binding is
 * what makes the preview in the admin show what the site will show. Two ways
 * of getting it wrong survive a page load and are only visible to whoever
 * opens the form: a selection that names the event provider without saying
 * which half of the calendar it wants, and one shown under a switch value
 * other than the half it reads.
 *
 * Both are copy-paste mistakes, which is exactly what a template declaring the
 * same selection once per layout style invites.
 */
final class EventSourceContractTest extends TestCase
{
    /**
     * Every selection on the event provider names the half it shows.
     */
    #[Test]
    public function everyEventSelectionNamesTheHalfItShows(): void
    {
        $offenders = [];

        foreach ($this->eventSelections() as $where => $selection) {
            if (!\in_array($selection['direction'], [EventDateScope::DIRECTION_UPCOMING, EventDateScope::DIRECTION_PAST], true)) {
                $offenders[] = $where . ' → direction "' . ($selection['direction'] ?? 'none') . '"';
            }
        }

        self::assertSame(
            [],
            $offenders,
            "These selections are built on the event provider without saying which half of the\n"
            . "calendar they show, so they silently fall back to the upcoming one:\n  "
            . \implode("\n  ", $offenders) . "\n"
            . 'Add <param name="direction" value="upcoming"/> or "past".',
        );
    }

    /**
     * Each selection shows under the switch value it reads.
     */
    #[Test]
    public function eachEventSelectionShowsUnderTheSourceItReads(): void
    {
        $offenders = [];

        foreach ($this->eventSelections() as $where => $selection) {
            $expected = "source == '" . $selection['direction'] . "'";

            if (!\str_contains((string) $selection['condition'], $expected)) {
                $offenders[] = $where . ' → shown under "' . $selection['condition'] . '"';
            }
        }

        self::assertSame(
            [],
            $offenders,
            "These selections show under a source other than the half of the calendar they\n"
            . "read, so the editor fills one list and the page renders another:\n  "
            . \implode("\n  ", $offenders),
        );
    }

    /**
     * Every selection declared on the event provider, across the templates.
     *
     * @return array<string, array{direction: string|null, condition: string|null}>
     */
    private function eventSelections(): array
    {
        $found = [];

        foreach ($this->templates() as $relativePath => $contents) {
            \preg_match_all(
                '/<property name="([^"]+)" type="smart_content"(.*?)<\/property>/s',
                $contents,
                $matches,
                \PREG_SET_ORDER,
            );

            foreach ($matches as $match) {
                [$whole, $name, $body] = $match;

                if (!\str_contains($body, '<param name="provider" value="' . EventSmartContentProvider::PROVIDER_TYPE . '"/>')) {
                    continue;
                }

                \preg_match('/<param name="direction" value="([^"]*)"\/>/', $body, $direction);
                \preg_match('/visibleCondition="([^"]*)"/', $whole, $condition);

                $found[$relativePath . ' → ' . $name] = [
                    'direction' => $direction[1] ?? null,
                    'condition' => $condition[1] ?? null,
                ];
            }
        }

        self::assertNotEmpty($found, 'No event selection found, the guard would pass on nothing.');

        return $found;
    }

    /**
     * Every template of the bundle, keyed by path relative to `config/templates/`.
     *
     * @return array<string, string>
     */
    private function templates(): array
    {
        $root = \dirname(__DIR__, 2) . '/config/templates';
        $found = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || 'xml' !== $file->getExtension()) {
                continue;
            }

            $found[\substr($file->getPathname(), \strlen($root) + 1)] = (string) \file_get_contents($file->getPathname());
        }

        return $found;
    }
}
