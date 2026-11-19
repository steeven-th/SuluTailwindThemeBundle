<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\EventSubscriber;

use ItechWorld\SuluTailwindThemeBundle\Event\ThemeCompileEvent;
use ItechWorld\SuluTailwindThemeBundle\Media\ImageRatioCatalog;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Draws the box of every project ratio in the compiled theme stylesheet.
 *
 * The bundle ratios have their `.iw-ratio--*` class in app.css. A project
 * ratio is only known once the formats are loaded, and an inline `style` is
 * out of the question, so its class goes into the one stylesheet the bundle
 * generates. The rules join the compiled file's fingerprint, so adding a ratio
 * renames the file and no browser keeps the old one.
 */
final class ImageRatioBoxSubscriber implements EventSubscriberInterface
{
    /**
     * @param ImageRatioCatalog $catalog The ratios, the project's included
     */
    public function __construct(
        private readonly ImageRatioCatalog $catalog,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [ThemeCompileEvent::class => 'onCompile'];
    }

    /**
     * @param ThemeCompileEvent $event The theme being compiled
     */
    public function onCompile(ThemeCompileEvent $event): void
    {
        foreach ($this->catalog->projectRatios() as $ratio) {
            $event->addRule(\sprintf('.iw-ratio--%s { aspect-ratio: %s; }', $ratio['token'], $ratio['css']));
        }
    }
}
