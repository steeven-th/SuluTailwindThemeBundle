<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Twig;

use ItechWorld\SuluTailwindThemeBundle\Form\FormSubmissionHandler;
use ItechWorld\SuluTailwindThemeBundle\ItechWorldSuluTailwindThemeBundle;
use ItechWorld\SuluTailwindThemeBundle\Admin\ThemeAdmin;
use ItechWorld\SuluTailwindThemeBundle\Service\BlockTemplateResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\CodeBlockPolicy;
use ItechWorld\SuluTailwindThemeBundle\Service\EmbedUrlValidator;
use ItechWorld\SuluTailwindThemeBundle\Service\FormSuccessResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\FormViewDuplicator;
use ItechWorld\SuluTailwindThemeBundle\Service\GoogleFontsResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\LanguageLabelResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeCompiler;
use ItechWorld\SuluTailwindThemeBundle\Service\ButtonResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\ThemeProvider;
use ItechWorld\SuluTailwindThemeBundle\Service\TitleMarkupRenderer;
use ItechWorld\SuluTailwindThemeBundle\Service\VariantColorSchemeResolver;
use ItechWorld\SuluTailwindThemeBundle\Service\VariantResolver;
use Psr\Log\LoggerInterface;
use Sulu\Component\Webspace\Analyzer\RequestAnalyzerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

/**
 * Twig extension providing theme-related functions and global variables.
 *
 * Exposes theme data (CSS path, fonts, tokens, menu config, block styles)
 * to Twig templates for rendering themed website pages.
 */
class ThemeExtension extends AbstractExtension implements GlobalsInterface, ResetInterface
{
    /**
     * Image width steps offered by `fragments/block-image-max-width.xml`.
     *
     * Each one must have a matching `.iw-imgw--*` rule in `app.css`, which is
     * what `ImageMaxWidthContractTest` guards.
     *
     * @var list<string>
     */
    public const IMAGE_MAX_WIDTH_STEPS = ['3xs', '2xs', 'xs', 'sm', 'md', 'lg', 'xl'];

    /**
     * Media share steps offered by `fragments/block-media-ratio.xml`.
     *
     * Named after the share the media zone takes. Each one must have a
     * matching `.iw-split-cols--*` rule in `app.css`, which is what
     * `MediaRatioContractTest` guards.
     *
     * @var list<string>
     */
    public const MEDIA_RATIO_STEPS = ['auto', '1-8', '1-6', '1-4', '1-3', '2-5', '1-2', '3-5', '2-3', '3-4'];

    /**
     * Vertical alignments offered by the two-zone blocks.
     *
     * Each one must have a matching `.iw-split-cols--align-*` rule in
     * `app.css`, which is what `MediaRatioContractTest` guards.
     *
     * @var list<string>
     */
    public const ZONES_ALIGN_VALUES = ['start', 'center', 'end', 'stretch'];

    /**
     * Sequence backing getUniqueId(), reset between requests by reset().
     */
    private int $uniqueIdCounter = 0;

    /**
     * Sequence backing getNextFormIndex(), reset between requests by reset().
     */
    private int $formIndexCounter = 0;

    /**
     * Configuration warnings already logged, so a page holding several forms
     * does not repeat the same line. Reset between requests by reset().
     *
     * @var array<int, string>
     */
    private array $loggedWarnings = [];

    public function __construct(
        private readonly ThemeProvider $themeProvider,
        private readonly ThemeCompiler $compiler,
        private readonly GoogleFontsResolver $fontsResolver,
        private readonly BlockTemplateResolver $blockTemplateResolver,
        private readonly EmbedUrlValidator $embedUrlValidator,
        private readonly CodeBlockPolicy $codeBlockPolicy,
        private readonly VariantColorSchemeResolver $colorSchemeResolver,
        private readonly FormViewDuplicator $formViewDuplicator,
        private readonly FormSuccessResolver $formSuccessResolver,
        private readonly LanguageLabelResolver $languageLabelResolver,
        private readonly TitleMarkupRenderer $titleMarkupRenderer,
        private readonly ?RequestAnalyzerInterface $requestAnalyzer = null,
        private readonly bool $turnstileEnabled = false,
        private readonly ?string $turnstileSiteKey = null,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly bool $debug = false,
    ) {
    }

    /**
     * Register Twig functions provided by this extension.
     *
     * @return array<TwigFunction> The list of Twig functions
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('iw_sulu_tailwind_theme_css_path', $this->getCssPath(...)),
            new TwigFunction('iw_sulu_tailwind_theme_fonts_link', $this->getFontsLink(...), [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('iw_sulu_block_style_template', $this->getBlockStyleTemplate(...)),
            new TwigFunction('iw_sulu_tailwind_theme_block_template', $this->getBlockTemplate(...)),
            new TwigFunction('iw_sulu_tailwind_theme_menu_config', $this->getMenuConfig(...)),
            new TwigFunction('iw_sulu_tailwind_theme_language_label', $this->getLanguageLabel(...)),
            new TwigFunction('iw_sulu_tailwind_theme_footer_config', $this->getFooterConfig(...)),
            new TwigFunction('iw_sulu_tailwind_theme_tokens', $this->getTokens(...)),
            new TwigFunction('iw_sulu_tailwind_theme_block_styles', $this->getBlockStyles(...)),
            new TwigFunction('iw_sulu_tailwind_theme_upload_max_size', $this->getUploadMaxSize(...)),
            new TwigFunction('iw_sulu_tailwind_theme_location_address', $this->getLocationAddress(...)),
            new TwigFunction('iw_sulu_tailwind_theme_radius_class', $this->getRadiusClass(...)),
            new TwigFunction('iw_sulu_tailwind_theme_effective_radius', $this->getEffectiveRadius(...)),
            new TwigFunction('iw_sulu_tailwind_theme_padding_class', $this->getPaddingClass(...)),
            new TwigFunction('iw_sulu_tailwind_theme_effective_padding', $this->getEffectivePadding(...)),
            new TwigFunction('iw_sulu_tailwind_theme_max_width_class', $this->getMaxWidthClass(...)),
            new TwigFunction('iw_sulu_tailwind_theme_image_max_width_class', $this->getImageMaxWidthClass(...)),
            new TwigFunction('iw_sulu_tailwind_theme_media_ratio_class', $this->getMediaRatioClass(...)),
            new TwigFunction('iw_sulu_tailwind_theme_zones_align_class', $this->getZonesAlignClass(...)),
            new TwigFunction('iw_sulu_tailwind_theme_focus_class', $this->getFocusClass(...)),
            new TwigFunction('iw_sulu_tailwind_theme_heading_tag', $this->getHeadingTag(...)),
            new TwigFunction('iw_sulu_tailwind_theme_title_markup', $this->getTitleMarkup(...), [
                'is_safe' => ['html'],
            ]),
            new TwigFunction('iw_sulu_tailwind_theme_title_text', $this->getTitleText(...)),
            new TwigFunction('iw_sulu_tailwind_theme_variant_slug', $this->getVariantSlug(...)),
            new TwigFunction('iw_sulu_tailwind_theme_variant_config', $this->getVariantConfig(...)),
            new TwigFunction('iw_sulu_tailwind_theme_button_slug', $this->getButtonSlug(...)),
            new TwigFunction('iw_sulu_tailwind_theme_color_scheme', $this->getColorScheme(...)),
            new TwigFunction('iw_sulu_tailwind_theme_with_color_scheme', $this->withColorScheme(...)),
            new TwigFunction('iw_sulu_tailwind_theme_reusable_form', $this->reusableForm(...)),
            new TwigFunction('iw_sulu_tailwind_theme_form_submitted', $this->isFormSubmitted(...)),
            new TwigFunction('iw_sulu_tailwind_theme_form_success_text', $this->getFormSuccessText(...)),
            new TwigFunction('iw_sulu_tailwind_theme_form_id', $this->getFormId(...)),
            new TwigFunction('iw_sulu_tailwind_theme_unique_id', $this->getUniqueId(...)),
            new TwigFunction('iw_sulu_tailwind_theme_next_form_index', $this->getNextFormIndex(...)),
            new TwigFunction('iw_sulu_tailwind_theme_embed_url', $this->getEmbedUrl(...)),
            new TwigFunction('iw_sulu_tailwind_theme_code_mode', $this->getCodeMode(...)),
            new TwigFunction('iw_sulu_tailwind_theme_code_srcdoc', $this->getCodeSrcdoc(...)),
            new TwigFunction('iw_sulu_tailwind_theme_has_form_bundle', $this->hasFormBundle(...)),
            new TwigFunction('iw_sulu_tailwind_theme_turnstile_site_key', $this->getTurnstileSiteKey(...)),
            new TwigFunction('iw_sulu_tailwind_theme_turnstile_status', $this->getTurnstileStatus(...)),
            new TwigFunction('iw_sulu_tailwind_theme_form_status', $this->getFormStatus(...)),
            new TwigFunction('iw_sulu_tailwind_theme_template_exists', $this->templateExists(...), [
                'needs_environment' => true,
            ]),
        ];
    }

    /**
     * Tell whether SuluFormBundle is installed.
     *
     * The form block only renders a SuluFormBundle form when it is, since the
     * bridge template calls form helpers that do not exist otherwise. Same
     * check as the one picking the admin form variant in the bundle class.
     *
     * @return bool True when SuluFormBundle is available
     */
    public function hasFormBundle(): bool
    {
        return class_exists(\Sulu\Bundle\FormBundle\SuluFormBundle::class);
    }

    /**
     * The outcome of the submission a form block is showing, if any.
     *
     * FormSubmissionHandler answers a submission by redirecting back to the
     * page with `?iw_form=<index>&iw_form_status=sent|error`, which is what
     * gets the request past the proxy cache. This reads that back for the block
     * being rendered, so a template asks "was I just submitted?" instead of
     * parsing the query string - and a page holding two forms only confirms the
     * one that was posted.
     *
     * @param int $formIndex The rank of the form block, as handed to the template
     *
     * @return string|null 'sent', 'error', or null when this block was not the one submitted
     */
    public function getFormStatus(int $formIndex): ?string
    {
        $request = $this->requestStack?->getMainRequest();

        if (null === $request || $formIndex !== $request->query->getInt(FormSubmissionHandler::FORM_PARAM)) {
            return null;
        }

        $status = $request->query->get(FormSubmissionHandler::STATUS_PARAM);

        return \in_array($status, [FormSubmissionHandler::STATUS_SENT, FormSubmissionHandler::STATUS_ERROR], true)
            ? $status
            : null;
    }

    /**
     * How usable the Turnstile configuration actually is.
     *
     * Both broken states below are invisible locally and only show up once
     * deployed, which is why the theme names them instead of rendering nothing:
     *
     *   - `missing_key`: the feature is on but no site key reached the app, so
     *     no widget renders - while the server-side check keeps running. The
     *     token is then always empty and *every* submission is refused. This is
     *     what an environment variable named differently on the host looks like.
     *   - `test_key`: the feature is on with Cloudflare's "always passes" test
     *     key. Normal in dev and CI, a decoy in production: the challenge
     *     validates every visitor, robots included.
     *
     * In production both are logged as warnings, once per request. In dev the
     * widget partial turns `missing_key` into a visible notice; `test_key` says
     * nothing there, since that is the documented way to work locally.
     *
     * @return string 'off', 'ready', 'missing_key' or 'test_key'
     */
    public function getTurnstileStatus(): string
    {
        if (!$this->turnstileEnabled) {
            return 'off';
        }

        if (null === $this->turnstileSiteKey || '' === $this->turnstileSiteKey) {
            $this->warnOnce('Cloudflare Turnstile is enabled but no site key is configured: no widget is rendered, while the server-side check still runs - every submission will be refused. Check the environment variable behind itech_world_sulu_tailwind_theme.turnstile.site_key.');

            return 'missing_key';
        }

        if (ItechWorldSuluTailwindThemeBundle::TURNSTILE_TEST_SITE_KEY === $this->turnstileSiteKey) {
            $this->warnOnce('Cloudflare Turnstile is enabled with the test site key, which validates every visitor: the challenge protects nothing. Set the real key in this environment.');

            return 'test_key';
        }

        return 'ready';
    }

    /**
     * Log a configuration warning once per request, in production only.
     *
     * In dev the same problems are shown on the page, where they cannot be
     * missed; here the point is to leave a trace on a server nobody is watching.
     *
     * @param string $message The warning to log
     */
    private function warnOnce(string $message): void
    {
        if ($this->debug || null === $this->logger || \in_array($message, $this->loggedWarnings, true)) {
            return;
        }

        $this->loggedWarnings[] = $message;
        $this->logger->warning($message);
    }

    /**
     * The Cloudflare Turnstile site key to render a widget with.
     *
     * Only for forms written in Twig template mode: the SuluFormBundle mode
     * gets its widget from a form field, which a hand-written form cannot use.
     * Without this, a project would have to declare the key a second time in
     * its own configuration, next to the one this bundle already forwards to
     * pixelopen - two places to keep in sync for one credential.
     *
     * The key is public by design, it ships in the HTML of every page carrying
     * the widget. The secret key is deliberately not exposed.
     *
     * @return string|null The site key, or null when the feature is off or unconfigured
     */
    public function getTurnstileSiteKey(): ?string
    {
        if (!$this->turnstileEnabled || null === $this->turnstileSiteKey || '' === $this->turnstileSiteKey) {
            return null;
        }

        return $this->turnstileSiteKey;
    }

    /**
     * Tell whether a Twig template can be loaded.
     *
     * Lets a template choose between a project override and a bundled default
     * without the blanket `ignore missing`, which turns a typo into silence.
     *
     * @param Environment $env  Injected by Twig (needs_environment)
     * @param string      $name Template name, e.g. 'forms/_sulu_form.html.twig'
     *
     * @return bool True when the template exists in the Twig loader
     */
    public function templateExists(Environment $env, string $name): bool
    {
        if ('' === $name) {
            return false;
        }

        return $env->getLoader()->exists($name);
    }

    /**
     * Resolve how a code block's markup must be executed.
     *
     * @param bool        $unsandboxedRequested The block's "unsandboxed" checkbox
     * @param string|null $code                 The pasted markup, checked against the length limit
     *
     * @return string 'sandboxed', 'raw', or 'too_long' when the snippet exceeds the limit
     */
    public function getCodeMode(bool $unsandboxedRequested, ?string $code = null): string
    {
        if (!$this->codeBlockPolicy->isWithinLengthLimit($code)) {
            return 'too_long';
        }

        return $this->codeBlockPolicy->resolveMode($unsandboxedRequested);
    }

    /**
     * Build the document served to the sandboxed iframe of a code block.
     *
     * The markup is wrapped rather than passed through, for two reasons that
     * would otherwise make the sandbox unusable in practice:
     *
     *  - **The theme stylesheet is linked in.** A sandboxed frame inherits none
     *    of the page's CSS, so a widget would render unstyled. A frame with an
     *    opaque origin can still fetch subresources by absolute URL, so linking
     *    the compiled theme CSS works and the embed looks native.
     *  - **A height reporter is injected.** The frame cannot resize its parent,
     *    which is the usual reason people give up on sandboxing. Since we own
     *    this document, a ResizeObserver posts the content height out and the
     *    embed_resize controller applies it.
     *
     * The pasted markup itself is emitted verbatim: sanitising here would defeat
     * the point of the block, and the sandbox — not escaping — is what contains
     * it. Escaping happens once, when this string is written into the `srcdoc`
     * attribute by the template.
     *
     * @param string      $code          The pasted markup
     * @param bool        $inheritStyles Whether to link the theme stylesheet
     * @param bool        $autoHeight    Whether to inject the height reporter
     *
     * @return string The full HTML document
     */
    public function getCodeSrcdoc(string $code, bool $inheritStyles = true, bool $autoHeight = true): string
    {
        $head = '<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';

        if ($inheritStyles) {
            $cssPath = $this->getCssPath();
            if ('' !== $cssPath) {
                $head .= '<link rel="stylesheet" href="' . htmlspecialchars($cssPath, \ENT_QUOTES, 'UTF-8') . '">';
            }
        }

        // Transparent background so the embed sits on the block's own surface,
        // and no default margin so the reported height matches the content.
        $head .= '<style>html,body{margin:0;padding:0;background:transparent;}</style>';

        $script = '';
        if ($autoHeight) {
            // postMessage targets '*': the parent cannot be identified by origin
            // from an opaque-origin frame. The parent authenticates the message
            // by comparing event.source with its own iframe's contentWindow.
            $script = '<script>(function(){'
                . 'var send=function(){parent.postMessage({type:"iw-embed-height",'
                . 'height:Math.ceil(document.documentElement.getBoundingClientRect().height)},"*");};'
                . 'if(window.ResizeObserver){new ResizeObserver(send).observe(document.documentElement);}'
                . 'window.addEventListener("load",send);document.addEventListener("DOMContentLoaded",send);'
                . '})();</script>';
        }

        return '<!doctype html><html><head>' . $head . '</head><body>' . $code . $script . '</body></html>';
    }

    /**
     * Validate an embed URL before it is written into an `src` attribute.
     *
     * Delegates to EmbedUrlValidator: https only, no credentials, and an
     * optional host allowlist from the bundle config. Returns null when the URL
     * must not be embedded, which lets the template skip the frame instead of
     * rendering a dangerous or broken one.
     *
     * @param string|null $url The URL entered by the editor
     *
     * @return string|null The URL when safe to embed, null otherwise
     */
    public function getEmbedUrl(?string $url): ?string
    {
        return $this->embedUrlValidator->validate($url);
    }

    /**
     * Generate an id that is unique within the current rendering.
     *
     * Sulu content blocks carry no stable identifier, yet some markup needs one:
     * grouping `<details name="…">` so a single panel stays open at a time,
     * wiring `aria-labelledby`, or scoping a style rule to one block instance.
     *
     * The counter is per-request (the extension is a service, rebuilt on every
     * request), so the same page always renders the same ids — unlike a random
     * value, which would churn the HTML on each render and break HTTP caching
     * diffs. Ids are only ever compared within one document, so a per-request
     * sequence is enough; they are not meant to be stable across requests.
     *
     * @param string $prefix A short identifier prefix (e.g. "accordion")
     *
     * @return string The unique id (e.g. "iw-accordion-1")
     */
    public function getUniqueId(string $prefix = 'iw'): string
    {
        // Keep the prefix usable as an HTML id and a CSS selector.
        $prefix = preg_replace('/[^a-z0-9-]/', '', strtolower($prefix)) ?: 'iw';

        return \sprintf('iw-%s-%d', $prefix, ++$this->uniqueIdCounter);
    }

    /**
     * Number the form block about to render.
     *
     * A page may hold several form blocks, and in Twig template mode they can
     * all point at the same project template. Rendered as is, that template
     * emits the same HTML ids twice: every `for` attribute then targets the
     * first field of the page, `aria-describedby` points at the wrong error,
     * and a post-submission anchor is ambiguous. The project cannot fix that on
     * its own, since nothing in the include context tells one block from
     * another, so the block hands its template this index to prefix ids with.
     *
     * The number is the rank of the form block on the page whatever mode each
     * block uses: mixing the two modes still yields 1, 2, 3. SuluFormBundle
     * mode has no use for it - FormViewDuplicator already suffixes the ids of a
     * form view rendered twice - but counting there too is what keeps the rank
     * predictable.
     *
     * @return int The 1-based rank of the form block in the current rendering
     */
    public function getNextFormIndex(): int
    {
        return ++$this->formIndexCounter;
    }

    /**
     * Start both per-request sequences over.
     *
     * Called by Symfony's services resetter between requests. Under php-fpm the
     * extension is rebuilt anyway, but a worker runtime (FrankenPHP,
     * RoadRunner) keeps the same instance alive: without this, ids would drift
     * from one request to the next and the same page would render differently
     * every time - straight into a shared cache, on this theme's page
     * templates.
     */
    public function reset(): void
    {
        $this->uniqueIdCounter = 0;
        $this->formIndexCounter = 0;
        $this->loggedWarnings = [];
    }

    /**
     * Resolve a stored block-variant value to its effective slug.
     *
     * Handles the migration from the legacy positional index to stable slugs:
     * a known slug is returned as-is, a numeric legacy index is mapped to the
     * variant at that position, otherwise the first variant is used.
     *
     * @param mixed             $variant  The stored variant value (slug or legacy index)
     * @param array<int, mixed> $variants The theme's block variants
     *
     * @return string The effective `.iw-variant--<slug>` slug, or '' if there is none
     */
    public function getVariantSlug(mixed $variant, array $variants): string
    {
        return VariantResolver::resolveSlug($variant, $variants);
    }

    /**
     * Resolve a stored block-variant value to its full config array.
     *
     * @param mixed             $variant  The stored variant value (slug or legacy index)
     * @param array<int, mixed> $variants The theme's block variants
     *
     * @return array<string, mixed> The matched variant config, or [] if none
     */
    /**
     * Resolve a stored button style to a slug the theme actually defines.
     *
     * For the places that put the style INTO a class name of their own, like
     * the carousel and timeline arrow modifiers: an empty value there would
     * render `--button-` and match nothing. Those need a real slug, not the
     * `iw-button--variant` alias a plain button falls back to.
     *
     * Never returns a hard-coded name. A theme names its own buttons, so
     * writing "primary" in a template matches the themes that happen to use
     * that name and silently unstyles every other one.
     *
     * @param string|null $stored The style stored on the block or the variant
     *
     * @return string A slug the theme defines, or empty when it defines none
     */
    public function getButtonSlug(?string $stored = null): string
    {
        return ButtonResolver::resolveSlug($stored, $this->themeProvider->getTokens()['buttons'] ?? []);
    }

    public function getVariantConfig(mixed $variant, array $variants): array
    {
        return VariantResolver::resolveConfig($variant, $variants);
    }

    /**
     * Resolve the color scheme a block variant renders on.
     *
     * For third-party widgets that live in an iframe and cannot inherit the
     * theme through CSS — they only accept a light/dark hint. Returns "auto"
     * when the surface color cannot be resolved, so the widget follows the
     * visitor's own preference instead of a wrong guess.
     *
     * @param mixed $variant       The stored variant value (slug or legacy index)
     * @param bool  $hasBackground Whether the block paints the variant background
     *
     * @return string One of "light", "dark" or "auto"
     */
    public function getColorScheme(mixed $variant, bool $hasBackground = true): string
    {
        return $this->colorSchemeResolver->resolve($variant, $hasBackground);
    }

    /**
     * Attach a color scheme to a form view, readable by its child widgets.
     *
     * Passing variables to `form(view, {...})` only fills the context of that
     * one render call: the FormView itself is left untouched, so a child
     * reading `form.parent.vars` would never see them. Writing to `vars`
     * up front is what makes the value reach the Turnstile widget, which sits
     * in an iframe and can only be told light or dark at render time.
     *
     * Typed as `object` on purpose: symfony/form is an optional dependency of
     * this bundle, so the signature must not force it to be installed.
     *
     * @param object $formView A Symfony FormView
     * @param string $scheme   One of "light", "dark" or "auto"
     *
     * @return object The same view, for chaining in a template
     */
    public function withColorScheme(object $formView, string $scheme): object
    {
        if (property_exists($formView, 'vars') && \is_array($formView->vars)) {
            $formView->vars['iw_color_scheme'] = $scheme;
        }

        return $formView;
    }

    /**
     * Return a form view that can be rendered now, even if it already was.
     *
     * Two blocks of a page may point at the same form, and Sulu hands both the
     * same FormView — which Symfony refuses to render twice. Returns the view
     * untouched the first time, and an independent copy with suffixed HTML ids
     * afterwards.
     *
     * @param object $formView A Symfony FormView
     *
     * @return object A renderable view
     */
    public function reusableForm(object $formView): object
    {
        return $this->formViewDuplicator->makeRenderable($formView);
    }

    /**
     * Tell whether this form was just submitted successfully.
     *
     * True on the request following SuluFormBundle's `?send=true` redirect, and
     * only for the form that was actually posted — the bridge template then
     * shows the confirmation instead of an empty form.
     *
     * @param object $formView A Symfony FormView
     *
     * @return bool True when the confirmation must replace the form
     */
    public function isFormSubmitted(object $formView): bool
    {
        return $this->formSuccessResolver->isSubmitted($formView);
    }

    /**
     * The success message to show for a submitted form.
     *
     * Returns the rich text an editor typed in the admin for the current
     * locale, or a translated default when that field was left empty — the
     * point being that a successful submission is never silent.
     *
     * @param object $formView A Symfony FormView
     *
     * @return string HTML, to be rendered raw
     */
    public function getFormSuccessText(object $formView): string
    {
        return $this->formSuccessResolver->getSuccessText($formView);
    }

    /**
     * The SuluFormBundle id of the form a view renders.
     *
     * Used to build the `iw-form-{id}` anchor the success redirect points at.
     *
     * @param object $formView A Symfony FormView
     *
     * @return int|null The form id, or null when the view is not a dynamic form
     */
    public function getFormId(object $formView): ?int
    {
        return $this->formSuccessResolver->getFormId($formView);
    }

    /**
     * Sanitize a configurable heading tag to a safe HTML heading element.
     *
     * Block titles expose a configurable heading level (h2/h3/h4) so editors
     * can fit a block into the page outline. The value may also come from
     * imported or programmatic content, so anything outside h1..h6 falls back
     * to the given default. Used when rendering a dynamic `<{{ tag }}>` element.
     *
     * @param string|null $tag     The requested tag (e.g. "h3")
     * @param string      $default The fallback tag when $tag is empty or invalid
     *
     * @return string A safe heading tag name (h1..h6)
     */
    public function getHeadingTag(?string $tag, string $default = 'h2'): string
    {
        $tag = strtolower(trim((string) $tag));

        return \in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $tag : $default;
    }

    /**
     * Render a title stored by the title editor to HTML.
     *
     * The stored value is plain text carrying `[[word]]` markers and real line
     * breaks. The renderer escapes it before inserting any tag, so the result
     * is safe to print unescaped - which is why this function is declared
     * `is_safe: html` and templates never need `|raw`.
     *
     * A marker naming a color gets that color, wherever the title sits. One
     * with no color follows the block variant, or the theme accent outside
     * one. Which buttons an editor is offered is decided by `title_editor` in
     * the bundle config, not here.
     *
     * @param string|null $text The stored title
     *
     * @return string HTML
     */
    public function getTitleMarkup(?string $text): string
    {
        return $this->titleMarkupRenderer->render($text);
    }

    /**
     * Strip a title down to its bare text.
     *
     * For anywhere a title must be plain: a `<title>` tag, a meta description,
     * an `alt` attribute, an aria-label.
     *
     * @param string|null $text The stored title
     *
     * @return string The title without markers or line breaks
     */
    public function getTitleText(?string $text): string
    {
        return $this->titleMarkupRenderer->toPlainText($text);
    }

    /**
     * Build the CSS focus class for a media focus point.
     *
     * Sulu stores the focus point on a 3×3 grid (X and Y each in 0..2, where
     * 0 = left/top, 1 = center, 2 = right/bottom). When the point is set this
     * returns a static positioning class — `focus-img-X-Y` (object-position on
     * an <img>) or `focus-bg-X-Y` (background-position on a CSS background) —
     * defined in app.css. It is only a client-side safety net: Sulu already
     * applies the focus point server-side when cropping outbound formats, so an
     * unset point (null) needs no class and returns an empty string.
     *
     * @param int|string|null $focusPointX The media focus point X (0..2), or null when unset
     * @param int|string|null $focusPointY The media focus point Y (0..2), or null when unset
     * @param string          $mode        The positioning target: "img" (object-position) or "bg" (background-position)
     *
     * @return string The focus class to emit, or an empty string when the point is unset or invalid
     */
    public function getFocusClass(int|string|null $focusPointX, int|string|null $focusPointY, string $mode = 'img'): string
    {
        if (!is_numeric($focusPointX) || !is_numeric($focusPointY)) {
            return '';
        }

        $x = (int) $focusPointX;
        $y = (int) $focusPointY;

        if ($x < 0 || $x > 2 || $y < 0 || $y > 2) {
            return '';
        }

        $prefix = 'bg' === $mode ? 'focus-bg' : 'focus-img';

        return sprintf('%s-%d-%d', $prefix, $x, $y);
    }

    /**
     * Get the CSS class to apply for a radius context.
     *
     * Returns the per-block override when set, otherwise the theme-default
     * utility class (`iw-radius--paragraph|card|image`) compiled by the
     * ThemeCompiler, which follows the active theme borders config without
     * baking the value into the rendered HTML.
     *
     * @param string      $context    The radius context: "paragraph", "card" or "image"
     * @param string|null $blockValue The per-block Tailwind class override, if any
     *
     * @return string The CSS class to emit
     */
    public function getRadiusClass(string $context, ?string $blockValue = null): string
    {
        if (null !== $blockValue && '' !== $blockValue) {
            return $blockValue;
        }

        return 'iw-radius--' . $context;
    }

    /**
     * Resolve the effective Tailwind radius class for a radius context.
     *
     * Unlike getRadiusClass() this resolves the theme borders config down to
     * the actual Tailwind class (e.g. "rounded-md"). Templates use it for
     * structural decisions (wrap an image or not, add spacing…) that depend
     * on whether a real radius is in effect. The result is baked into the
     * rendered HTML, so such structure follows the theme value at render
     * time (same caching caveat as block variants).
     *
     * @param string      $context    The radius context: "paragraph", "card" or "image"
     * @param string|null $blockValue The per-block Tailwind class override, if any
     *
     * @return string The effective Tailwind class, or an empty string when none
     */
    /**
     * The padding class a block puts on itself for one of its edges.
     *
     * Mirrors getRadiusClass: an empty block value means "follow the theme",
     * and the theme default travels as a utility class the stylesheet defines
     * rather than as a value, so a change of theme moves every block that never
     * chose a padding of its own without touching any content.
     *
     * Empty and zero are NOT the same thing here. `pt-0` is a block saying it
     * wants no padding, and it must keep winning over a theme that has one.
     *
     * @param string      $context    top, bottom or lateral
     * @param string|null $blockValue The padding stored on the block, if any
     *
     * @return string The class to render
     */
    public function getPaddingClass(string $context, ?string $blockValue = null): string
    {
        if (null !== $blockValue && '' !== $blockValue) {
            return $blockValue;
        }

        return 'iw-padding--' . $context;
    }

    /**
     * The padding a block ends up with, as a Tailwind class name.
     *
     * The class the template renders may be a utility that only the stylesheet
     * can resolve, so anything that has to REASON about the padding needs this
     * instead. The block wrapper does: it drops the radius of a block whose
     * edges reach the viewport, and it decides that by looking at the lateral
     * padding. Reading the raw value there would treat "follow the theme" as
     * "no padding" and strip the corners of every block that never set one.
     *
     * @param string      $context    top, bottom or lateral
     * @param string|null $blockValue The padding stored on the block, if any
     *
     * @return string The effective Tailwind class, e.g. "pt-5"
     */
    public function getEffectivePadding(string $context, ?string $blockValue = null): string
    {
        if (null !== $blockValue && '' !== $blockValue) {
            return $blockValue;
        }

        $defaults = $this->themeProvider->getTokens()['defaults'] ?? [];

        return match ($context) {
            'top' => (string) ($defaults['blockPaddingTop'] ?? 'pt-5'),
            'bottom' => (string) ($defaults['blockPaddingBottom'] ?? 'pb-5'),
            default => (string) ($defaults['blockPaddingLateral'] ?? 'px-5'),
        };
    }

    public function getEffectiveRadius(string $context, ?string $blockValue = null): string
    {
        if (null !== $blockValue && '' !== $blockValue) {
            return $blockValue;
        }

        $borders = $this->themeProvider->getTokens()['borders'] ?? [];
        // Legacy pre-3.0.0 `radius` key read as cardRadius fallback
        $card = (string) ($borders['cardRadius'] ?? $borders['radius'] ?? '');

        return match ($context) {
            'paragraph' => (string) ($borders['paragraphRadius'] ?? ''),
            'image' => (string) ($borders['imageRadius'] ?? $card),
            default => $card,
        };
    }

    /**
     * Resolve the classes capping a block's width.
     *
     * A block spans the page container by default, which leaves a title and
     * two lines of text stretched over the whole screen. The theme sets a
     * site-wide maximum under Defaults > Blocks and a block overrides it from
     * its own Settings section: an empty value follows the theme, "none" opts
     * this block out of a theme that constrains everything.
     *
     * The theme value only reaches the blocks listed in its scope, picked in
     * the admin from the Maximum width modal. An explicit per-block choice
     * ignores the scope entirely, in both directions - it is the editor
     * looking at that very block.
     *
     * The step class is only added for an override, so a block following the
     * theme reads `--iw-blocks-max-width` at render time and moves with it.
     * Nothing is returned when no constraint applies: `.iw-block-maxw` sits
     * outside `@layer`, so an unconstrained `max-width: none` would beat the
     * `.container` utility and widen the block instead of leaving it alone.
     *
     * @param string|null $blockValue The per-block container step, if any
     * @param string|null $blockType  The block type, to match against the theme scope
     * @param string|null $style      The block style, resolved before matching
     *
     * @return string The classes to apply, or an empty string when the block is unconstrained
     */
    public function getMaxWidthClass(?string $blockValue = null, ?string $blockType = null, ?string $style = null): string
    {
        $blockValue = (string) $blockValue;

        if ('none' === $blockValue) {
            return '';
        }

        if ('' !== $blockValue) {
            return 'iw-block-maxw iw-maxw--' . $blockValue;
        }

        $defaults = $this->themeProvider->getTokens()['defaults'] ?? [];
        $themeValue = (string) ($defaults['blockMaxWidth'] ?? 'none');

        if ('' === $themeValue || 'none' === $themeValue) {
            return '';
        }

        return $this->isInMaxWidthScope($defaults['blockMaxWidthScope'] ?? null, $blockType, $style)
            ? 'iw-block-maxw'
            : '';
    }

    /**
     * Class capping the width of the images a block renders.
     *
     * Unlike the block width, this one has no site-wide token behind it: an
     * image cap is a per block decision, so an empty value means the images
     * keep the full width of whatever holds them.
     *
     * The value is checked against the steps the fragment offers, so a stale
     * or hand-edited value yields no class rather than a class that matches
     * nothing in the stylesheet.
     *
     * @param string|null $value step key, from the `imageMaxWidth` property
     *
     * @return string the utility class, or an empty string for no constraint
     */
    public function getImageMaxWidthClass(?string $value = null): string
    {
        $value = (string) $value;

        return \in_array($value, self::IMAGE_MAX_WIDTH_STEPS, true)
            ? 'iw-imgw--' . $value
            : '';
    }

    /**
     * Class sharing the width of a two-zone block between its two zones.
     *
     * The editor's value wins, and the style's own share takes over when it is
     * empty or unknown. That fallback is the whole point: the setting is added
     * to blocks that already ship, and an empty value has to leave them
     * looking exactly as they did.
     *
     * @param string|null $value    step key, from the `mediaRatio` property
     * @param string      $fallback the calling style's own share, e.g. '1-2'
     *
     * @return string the modifier class for `.iw-split-cols`
     */
    public function getMediaRatioClass(?string $value = null, string $fallback = '1-2'): string
    {
        $value = (string) $value;

        if (!\in_array($value, self::MEDIA_RATIO_STEPS, true)) {
            $value = \in_array($fallback, self::MEDIA_RATIO_STEPS, true) ? $fallback : '1-2';
        }

        return 'iw-split-cols--' . $value;
    }

    /**
     * Class lining up the two zones of a block vertically.
     *
     * Same contract as the width split: the editor's value wins, the style's
     * own alignment takes over when it is empty, so adding the field moves
     * nothing until somebody asks.
     *
     * @param string|null $value    one of `ZONES_ALIGN_VALUES`, from `zonesAlign`
     * @param string      $fallback the calling style's own alignment
     *
     * @return string the modifier class for `.iw-split-cols`
     */
    public function getZonesAlignClass(?string $value = null, string $fallback = 'stretch'): string
    {
        $value = (string) $value;

        if (!\in_array($value, self::ZONES_ALIGN_VALUES, true)) {
            $value = \in_array($fallback, self::ZONES_ALIGN_VALUES, true) ? $fallback : 'stretch';
        }

        return 'iw-split-cols--align-' . $value;
    }

    /**
     * Tell whether a block follows the theme-wide maximum width.
     *
     * The scope holds block types (`"text"`, the whole block whatever its
     * style) and type:style pairs (`"gallery:grid"`). A theme saved before the
     * scope existed, or one whose scope was never touched, falls back to the
     * suggested selection rather than to everything: turning the width on then
     * capping an image gallery is not what the editor asked for.
     *
     * @param mixed       $scope     The stored scope, a list of entries or null
     * @param string|null $blockType The block type being rendered
     * @param string|null $style     The stored style, resolved to the one that renders
     */
    private function isInMaxWidthScope(mixed $scope, ?string $blockType, ?string $style): bool
    {
        $blockType = trim((string) $blockType);

        // A block rendered outside the dispatcher carries no type. Capping it
        // is the closest thing to the pre-scope behavior, and a block that
        // does not want it says so in its own field.
        if ('' === $blockType) {
            return true;
        }

        $entries = \is_array($scope) ? $scope : ThemeAdmin::MAX_WIDTH_SUGGESTED_SCOPE;

        if (\in_array($blockType, $entries, true)) {
            return true;
        }

        $resolvedStyle = $this->blockTemplateResolver->resolveStyle($blockType, $style);

        return null !== $resolvedStyle && \in_array($blockType . ':' . $resolvedStyle, $entries, true);
    }

    /**
     * Format the structured address of a Sulu location value as a multi-line string.
     *
     * Builds "number street\ncode town\ncountry" from the available fields,
     * skipping empty parts. Used by the location block styles and the form
     * location widget (display + map popup).
     *
     * @param array<string, mixed>|null $location The Sulu location value (lat, long, street, number, code, town, country)
     *
     * @return string The formatted address, or an empty string when no address fields are filled
     */
    public function getLocationAddress(?array $location): string
    {
        if (null === $location) {
            return '';
        }

        $parts = [];

        $street = trim((string) ($location['street'] ?? ''));
        if ('' !== $street) {
            $number = trim((string) ($location['number'] ?? ''));
            $parts[] = '' !== $number ? $number . ' ' . $street : $street;
        }

        $cityLine = trim(implode(' ', array_filter([
            trim((string) ($location['code'] ?? '')),
            trim((string) ($location['town'] ?? '')),
        ], static fn (string $value): bool => '' !== $value)));
        if ('' !== $cityLine) {
            $parts[] = $cityLine;
        }

        $country = trim((string) ($location['country'] ?? ''));
        if ('' !== $country) {
            $parts[] = $country;
        }

        return implode("\n", $parts);
    }

    /**
     * Register global Twig variables.
     *
     * Provides `iw_sulu_tailwind_theme` global containing resolved tokens
     * for direct access in templates.
     *
     * @return array<string, mixed> The global variables
     */
    public function getGlobals(): array
    {
        return [
            'iw_sulu_tailwind_theme' => $this->themeProvider->getTokens(),
        ];
    }

    /**
     * Get the web-accessible path to the compiled CSS file.
     *
     * @return string The CSS path, or empty string if no theme is active
     */
    public function getCssPath(): string
    {
        return $this->themeProvider->getCssPath() ?? '';
    }

    /**
     * Get a <link> HTML tag for loading Google Fonts.
     *
     * Returns a preconnect hint and the Google Fonts stylesheet link
     * for optimal font loading performance.
     *
     * @return string The HTML link tags, or empty string if no fonts are configured
     */
    public function getFontsLink(): string
    {
        $tokens = $this->themeProvider->getTokens();
        $typography = $tokens['typography'] ?? [];
        $fontsUrl = $this->fontsResolver->resolve($typography);

        if (null === $fontsUrl) {
            return '';
        }

        $escapedUrl = htmlspecialchars($fontsUrl, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return '<link rel="preconnect" href="https://fonts.googleapis.com">'
            . "\n"
            . '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            . "\n"
            . '<link rel="stylesheet" href="' . $escapedUrl . '">';
    }

    /**
     * Resolve the style template of a content block to an existing template.
     *
     * Used by the shared block dispatcher to render any block through a
     * guaranteed-existing style template: the explicit style when valid, the
     * curated per-type default otherwise, and the first available style as a
     * last-resort safety net. Returns null only when the block type has no
     * renderable style at all, letting the dispatcher skip it instead of
     * crashing on a missing template.
     *
     * @param string      $blockType The block type identifier (e.g. "text_images")
     * @param string|null $style     The selected style, if any (e.g. "overlay")
     *
     * @return string|null The namespaced Twig template name, or null when none
     */
    public function getBlockTemplate(string $blockType, ?string $style = null): ?string
    {
        return $this->blockTemplateResolver->resolve($blockType, $style);
    }

    /**
     * Get the Twig template filename for a block style.
     *
     * Looks up the block styles configuration to find the template
     * associated with a specific block type and style key.
     *
     * Block styles structure:
     *   styles: [{key, label, twig, default?}, ...]
     *
     * @param string      $blockType The block type identifier
     * @param string|null $styleKey  The style variant key (null for default)
     *
     * @return string|null The Twig template filename, or null if not found
     */
    public function getBlockStyleTemplate(string $blockType, ?string $styleKey = null): ?string
    {
        $blockStyles = $this->themeProvider->getBlockStyles();
        $blockConfig = $blockStyles[$blockType] ?? null;

        if (null === $blockConfig || empty($blockConfig['styles'])) {
            return null;
        }

        $styles = $blockConfig['styles'];

        // Find by specific style key
        if (null !== $styleKey) {
            foreach ($styles as $style) {
                if (($style['key'] ?? '') === $styleKey) {
                    return $style['twig'] ?? null;
                }
            }

            return null;
        }

        // Find the default style
        foreach ($styles as $style) {
            if (!empty($style['default'])) {
                return $style['twig'] ?? null;
            }
        }

        // Fallback to the first style
        return $styles[0]['twig'] ?? null;
    }

    /**
     * Get the block styles configuration for the active theme.
     *
     * @return array<string, mixed> The block styles
     */
    public function getBlockStyles(): array
    {
        return $this->themeProvider->getBlockStyles();
    }

    /**
     * Get the menu configuration for the active theme.
     *
     * Injects the webspace name as `siteName` when available (website context).
     *
     * @return array<string, mixed> The menu configuration
     */
    public function getMenuConfig(): array
    {
        $config = $this->themeProvider->getMenuConfig();

        if (!empty($config) && null !== $this->requestAnalyzer) {
            $webspace = $this->requestAnalyzer->getWebspace();
            if (null !== $webspace) {
                $config['siteName'] = $webspace->getName();
            }
        }

        return $config;
    }

    /**
     * Label a locale for the language switcher.
     *
     * The locales themselves come from Sulu's `localizations` view parameter,
     * which is built from the webspace XML - adding a language there is all it
     * takes for it to appear in the menu.
     *
     * @param string      $locale        The locale to label (e.g. "fr", "pt_BR")
     * @param string      $format        "code" (FR), "native" (Français) or "translated"
     * @param string|null $displayLocale Locale to translate into; defaults to the current request
     *
     * @return string A displayable label, never empty for a non-empty locale
     */
    public function getLanguageLabel(string $locale, string $format = LanguageLabelResolver::FORMAT_CODE, ?string $displayLocale = null): string
    {
        return $this->languageLabelResolver->resolve(
            $locale,
            $format,
            $displayLocale ?? $this->requestAnalyzer?->getCurrentLocalization()?->getLocale(),
        );
    }

    /**
     * Get the footer configuration for the active theme.
     *
     * Injects the webspace name as `siteName` when available (website context),
     * mirroring {@see getMenuConfig()} so footer partials can display it.
     *
     * @return array<string, mixed> The footer configuration
     */
    public function getFooterConfig(): array
    {
        $config = $this->themeProvider->getFooterConfig();

        if (!empty($config) && null !== $this->requestAnalyzer) {
            $webspace = $this->requestAnalyzer->getWebspace();
            if (null !== $webspace) {
                $config['siteName'] = $webspace->getName();
            }
        }

        return $config;
    }

    /**
     * Get the raw design tokens for the active theme.
     *
     * @return array<string, mixed> The design tokens
     */
    public function getTokens(): array
    {
        return $this->themeProvider->getTokens();
    }

    /**
     * Get the maximum upload file size allowed by the server.
     *
     * Returns the smallest value between PHP's upload_max_filesize
     * and post_max_size, as both a human-readable label and raw bytes.
     *
     * @return array{label: string, bytes: int} The maximum upload size
     */
    public function getUploadMaxSize(): array
    {
        $uploadMax = $this->parseIniSize(\ini_get('upload_max_filesize') ?: '8M');
        $postMax = $this->parseIniSize(\ini_get('post_max_size') ?: '8M');

        // post_max_size = 0 means unlimited
        $maxBytes = $postMax > 0 ? min($uploadMax, $postMax) : $uploadMax;

        if ($maxBytes >= 1048576) {
            $label = round($maxBytes / 1048576) . ' MB';
        } else {
            $label = round($maxBytes / 1024) . ' KB';
        }

        return ['label' => $label, 'bytes' => $maxBytes];
    }

    /**
     * Parse a PHP ini size value (e.g. "8M", "128K") to bytes.
     *
     * @param string $value The ini value
     *
     * @return int The size in bytes
     */
    private function parseIniSize(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $numericValue = (int) $value;

        return match ($last) {
            'g' => $numericValue * 1073741824,
            'm' => $numericValue * 1048576,
            'k' => $numericValue * 1024,
            default => $numericValue,
        };
    }
}
