<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Tests\Admin;

use ItechWorld\SuluTailwindThemeBundle\Admin\AppearanceWebspaceAdmin;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;

/**
 * Where the appearance switch is grafted, and where it must not be.
 *
 * The views are named after a template group a project defines, so the admin
 * matches names rather than listing them. That matching is the whole behaviour
 * of the class, and it fails silently in both directions: too narrow and the
 * switch never appears, too wide and it shows up on forms holding no
 * appearance field at all.
 */
final class AppearanceWebspaceAdminTest extends TestCase
{
    private const SWITCH_ACTION = 'iw_sulu_tailwind_theme.appearance_webspace';

    #[Test]
    public function itAddsTheSwitchToEveryArticleContentForm(): void
    {
        $views = $this->configuredViews();

        self::assertContains(self::SWITCH_ACTION, $views['sulu_article.article.edit_tabs_default.content']);
        self::assertContains(self::SWITCH_ACTION, $views['sulu_article.article.add_tabs_default.content']);
        // A project naming its own template groups gets the same treatment.
        self::assertContains(self::SWITCH_ACTION, $views['sulu_article.article.edit_tabs_news.content']);
    }

    /**
     * A snippet reaches a site by being assigned to its areas, and several
     * sites can assign the same one.
     */
    #[Test]
    public function itAddsTheSwitchToTheSnippetContentForm(): void
    {
        $views = $this->configuredViews();

        self::assertContains(self::SWITCH_ACTION, $views['sulu_snippet.snippet.edit_tabs.content']);
        self::assertNotContains(self::SWITCH_ACTION, $views['sulu_snippet.snippet.edit_tabs.settings']);
    }

    #[Test]
    public function itKeepsTheToolbarActionsThatWereAlreadyThere(): void
    {
        $views = $this->configuredViews();

        self::assertContains('sulu_admin.save_with_publishing', $views['sulu_article.article.edit_tabs_default.content']);
    }

    /**
     * Seo, excerpt and settings hold no appearance field, so a switch sitting
     * there would promise something the form cannot do.
     */
    #[Test]
    public function itLeavesTheOtherArticleFormsAlone(): void
    {
        $views = $this->configuredViews();

        self::assertNotContains(self::SWITCH_ACTION, $views['sulu_article.article.edit_tabs_default.seo']);
        self::assertNotContains(self::SWITCH_ACTION, $views['sulu_article.article.edit_tabs_default.settings']);
    }

    /**
     * A page belongs to a single site, so it has nothing to switch between.
     */
    #[Test]
    public function itLeavesPagesAlone(): void
    {
        $views = $this->configuredViews();

        self::assertNotContains(self::SWITCH_ACTION, $views['sulu_page.page_edit_form.content']);
    }

    /**
     * Sulu configures admins by descending priority, and SuluArticleBundle
     * uses the default. Anything but a lower priority and the article views
     * may not exist yet, leaving the switch silently unattached.
     */
    #[Test]
    public function itRunsAfterTheBundleBuildingTheArticleViews(): void
    {
        self::assertLessThan(0, AppearanceWebspaceAdmin::getPriority());
    }

    /**
     * The toolbar actions of each view, keyed by view name.
     *
     * @return array<string, list<string>>
     */
    private function configuredViews(): array
    {
        $factory = new ViewBuilderFactory();
        $collection = new ViewCollection();

        $forms = [
            'sulu_article.article.edit_tabs_default.content',
            'sulu_article.article.add_tabs_default.content',
            'sulu_article.article.edit_tabs_news.content',
            'sulu_article.article.edit_tabs_default.seo',
            'sulu_article.article.edit_tabs_default.settings',
            'sulu_snippet.snippet.edit_tabs.content',
            'sulu_snippet.snippet.edit_tabs.settings',
            'sulu_page.page_edit_form.content',
        ];

        foreach ($forms as $name) {
            $collection->add(
                $factory->createFormViewBuilder($name, '/' . $name)
                    ->setResourceKey('articles')
                    ->setFormKey('article')
                    ->addToolbarActions([new ToolbarAction('sulu_admin.save_with_publishing')]),
            );
        }

        // A tab view is not a form and has no toolbar actions to add to. Its
        // name does not end in `.content`, but a builder that is not a form is
        // the case the admin has to survive rather than assume away.
        $collection->add($factory->createResourceTabViewBuilder(
            'sulu_article.article.edit_tabs_default',
            '/articles/:id',
        )->setResourceKey('articles'));

        (new AppearanceWebspaceAdmin())->configureViews($collection);

        $views = [];

        foreach ($collection->all() as $name => $builder) {
            $actions = $builder->getView()->getOption('toolbarActions') ?? [];
            $views[$name] = array_map(
                static fn (object $action): string => $action->getType(),
                $actions,
            );
        }

        return $views;
    }
}
