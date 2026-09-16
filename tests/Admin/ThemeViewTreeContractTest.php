<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use ItechWorld\SuluTailwindThemeBundle\Admin\ThemeAdmin;
use ItechWorld\SuluTailwindThemeBundle\Repository\ThemeConfigRepository;
use ItechWorld\SuluTailwindThemeBundle\Repository\WebspaceThemeRepository;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsCatalog;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeConfigResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Sulu\Component\Webspace\Manager\WebspaceManagerInterface;

/**
 * Guards the shape of the theme form, which is a tree and not a flat list.
 *
 * A group of settings is a view of its own, of a type the bundle registers,
 * whose children are the forms it holds. Three things have to line up for it
 * to work, and none of them fails loudly: the group has to carry the type of
 * the registered view, its children have to name the group as their parent,
 * and one of them has to carry a tab priority, which is what Sulu redirects to
 * when the group itself is clicked. Miss the last one and clicking the group
 * lands on a blank page.
 *
 * The views are built here with the real Sulu factory, so the test reads the
 * tree the admin actually declares.
 */
final class ThemeViewTreeContractTest extends TestCase
{
    /**
     * The group view carries the type of the nested tabs view the bundle registers.
     */
    #[Test]
    public function theGroupIsRenderedByTheNestedTabsView(): void
    {
        $views = $this->views();

        self::assertArrayHasKey(ThemeAdmin::EDIT_FORM_VIEW . '.defaults_group', $views);

        $group = $views[ThemeAdmin::EDIT_FORM_VIEW . '.defaults_group'];

        self::assertSame(
            'iw_sulu_tailwind_theme.nested_tabs',
            $group->getType(),
            'The group must be rendered by the view registered in index.js, not by Sulu\'s own tabs view, '
            . 'which does not hand the record down to the form below it.',
        );
        self::assertSame(ThemeAdmin::EDIT_FORM_VIEW, $group->getParent());
    }

    /**
     * Every group holds at least two forms, one of which opens it.
     */
    #[Test]
    public function everyGroupHoldsFormsAndOpensOnOneOfThem(): void
    {
        $views = $this->views();

        $groups = [];
        foreach ($views as $name => $view) {
            if ('iw_sulu_tailwind_theme.nested_tabs' === $view->getType()) {
                $groups[$name] = [];
            }
        }

        self::assertNotEmpty($groups, 'No group of tabs is declared, so this test guards nothing.');

        foreach ($views as $view) {
            $parent = $view->getParent();
            if (null !== $parent && \array_key_exists($parent, $groups)) {
                $groups[$parent][] = $view;
            }
        }

        foreach ($groups as $name => $children) {
            self::assertGreaterThan(
                1,
                \count($children),
                \sprintf('%s is a group of tabs holding fewer than two, which reads as a tab with a detour.', $name),
            );

            $prioritised = array_filter(
                $children,
                static fn ($child): bool => null !== ($child->getOption('tabPriority') ?? null),
            );

            self::assertCount(
                1,
                $prioritised,
                \sprintf(
                    '%s must have exactly one child carrying a tabPriority: that is the tab Sulu opens when the '
                    . 'group is clicked, and without it the group leads nowhere.',
                    $name,
                ),
            );
        }
    }

    /**
     * The views the admin declares, keyed by name.
     *
     * @return array<string, \Sulu\Bundle\AdminBundle\Admin\View\View>
     */
    private function views(): array
    {
        $securityChecker = new class implements SecurityCheckerInterface {
            public function checkPermission($subject, $permission, $object = null): void
            {
            }

            public function hasPermission($subject, $permission, $object = null): bool
            {
                return true;
            }
        };

        $admin = new ThemeAdmin(
            new ViewBuilderFactory(),
            $securityChecker,
            $this->createStub(ThemeConfigRepository::class),
            $this->createStub(GoogleFontsCatalog::class),
            $this->createStub(WebspaceThemeRepository::class),
            $this->createStub(WebspaceManagerInterface::class),
            $this->createStub(ThemeConfigResolver::class),
            true,
        );

        $collection = new ViewCollection();
        $admin->configureViews($collection);

        $views = [];
        foreach ($collection->all() as $builder) {
            $view = $builder->getView();
            $views[$view->getName()] = $view;
        }

        return $views;
    }
}
