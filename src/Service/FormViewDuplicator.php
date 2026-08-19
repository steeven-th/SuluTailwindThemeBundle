<?php

declare(strict_types=1);

namespace ItechWorld\SuluTailwindThemeBundle\Service;

/**
 * Lets the same form be rendered more than once on a page.
 *
 * Two form blocks pointing at the same form is a legitimate layout — a contact
 * form at the top and at the bottom of a long landing page. Sulu's resource
 * loader hands both blocks the *same* FormView instance, and Symfony refuses to
 * render one twice:
 *
 *     Field "dynamic_form1" has already been rendered, save the result of
 *     previous render call to a variable and output that instead.
 *
 * The refusal is not arbitrary: rendering the same view twice would emit the
 * same HTML ids twice, which breaks every `for` attribute on the page. So this
 * duplicates the view tree and suffixes the ids, rather than just clearing the
 * "already rendered" flag.
 *
 * `full_name` is deliberately left untouched: it is the POST name, and Sulu
 * matches a submission against it. Both copies therefore submit to the same
 * form — which is the point, they *are* the same form — and a submission with
 * errors shows those errors on both.
 *
 * Everything is typed as `object` rather than FormView: symfony/form is an
 * optional dependency of this bundle and must not become required. Copies are
 * built with `new ($view::class)`, so the class is never named here either.
 */
class FormViewDuplicator
{
    /**
     * Number of times a form has been handed out, per request.
     *
     * The service is not shared between requests, so the counter restarts at
     * every page render and the generated ids stay stable for a given page.
     */
    private int $copies = 1;

    /**
     * Return a view that can safely be rendered now.
     *
     * The first call for a given form returns it untouched — the common case
     * costs nothing. Later calls return an independent copy with suffixed ids.
     *
     * @param object $formView A Symfony FormView
     *
     * @return object A view that has not been rendered yet
     */
    public function makeRenderable(object $formView): object
    {
        if (!method_exists($formView, 'isRendered') || !$formView->isRendered()) {
            return $formView;
        }

        return $this->duplicate($formView, null, '-' . ++$this->copies);
    }

    /**
     * Recursively copy a view tree, suffixing every HTML id.
     *
     * @param object      $view   The view to copy
     * @param object|null $parent The copied parent, or null for the root
     * @param string      $suffix Suffix appended to every id
     *
     * @return object The copied view
     */
    private function duplicate(object $view, ?object $parent, string $suffix): object
    {
        $class = $view::class;
        $copy = new $class($parent);

        $vars = $view->vars;

        if (isset($vars['id']) && \is_string($vars['id'])) {
            $vars['id'] .= $suffix;
        }

        // FormType::buildView() stores the view inside its own vars; left as
        // is, the copy would hand templates the original view back.
        if (isset($vars['form']) && $vars['form'] === $view) {
            $vars['form'] = $copy;
        }

        $copy->vars = $vars;

        foreach ($view->children as $name => $child) {
            $copy->children[$name] = $this->duplicate($child, $copy, $suffix);
        }

        return $copy;
    }
}
