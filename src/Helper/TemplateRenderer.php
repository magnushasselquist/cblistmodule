<?php

/**
 * @package     Community Builder list module
 * @copyright   Copyright (C) 2014 Magnus Hasselquist. All rights reserved.
 * @copyright   Copyright (C) 2021 Tazzios. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Hasselquist\Module\CbList\Site\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Replaces [tag] placeholders in the module template with user field values and rule output.
 *
 * Tags are only ever expanded inside administrator authored text (the module template and the
 * rule HTML). User supplied field values are inserted verbatim and are never scanned for tags,
 * so a user cannot inject placeholders through their own profile data.
 *
 * @since  4.0.0
 */
final class TemplateRenderer
{
    /**
     * Maximum nesting depth for rules that reference other rules.
     *
     * @var    int
     * @since  4.0.0
     */
    private const MAX_DEPTH = 6;

    /**
     * Pattern matching a [tag] placeholder.
     *
     * @var    string
     * @since  4.0.0
     */
    private const TAG_PATTERN = '/\[([A-Za-z0-9_.\-]+)\]/';

    /**
     * Rules keyed by lower case tag name. Each rule holds the keys
     * 'html', 'htmlNo' (both strings) and 'access' (array of view level ids, empty = everybody).
     *
     * @var    array<string, array{html: string, htmlNo: string, access: int[]}>
     * @since  4.0.0
     */
    private array $rules;

    /**
     * View levels the current visitor is authorised for.
     *
     * @var    int[]
     * @since  4.0.0
     */
    private array $viewLevels;

    /**
     * Constructor.
     *
     * @param   array  $rules       Normalised rules keyed by lower case tag name.
     * @param   int[]  $viewLevels  View levels of the current visitor.
     *
     * @since   4.0.0
     */
    public function __construct(array $rules, array $viewLevels)
    {
        $this->rules      = $rules;
        $this->viewLevels = array_map('intval', $viewLevels);
    }

    /**
     * Normalise the raw "rules" subform value from the module parameters.
     *
     * @param   mixed  $subform  The value of the rules parameter (object, array or null).
     *
     * @return  array<string, array{html: string, htmlNo: string, access: int[]}>
     *
     * @since   4.0.0
     */
    public static function normaliseRules($subform): array
    {
        $rules = [];

        foreach ((array) $subform as $rule) {
            $rule = (array) $rule;
            $name = strtolower(trim((string) ($rule['tag_name'] ?? '')));

            if ($name === '') {
                continue;
            }

            $access = $rule['accesslevel'] ?? [];

            if (!\is_array($access)) {
                $access = $access === '' || $access === null ? [] : [$access];
            }

            $rules[$name] = [
                'html'   => (string) ($rule['htmlcode'] ?? ''),
                'htmlNo' => (string) ($rule['htmlcode_no'] ?? ''),
                'access' => array_values(array_filter(array_map('intval', $access))),
            ];
        }

        return $rules;
    }

    /**
     * Render a template for one user.
     *
     * @param   string  $template  The template containing [tag] placeholders.
     * @param   array   $values    Field values keyed by lower case tag name. Each entry holds
     *                             'value' (already escaped string) and 'hasData' (bool).
     *
     * @return  string
     *
     * @since   4.0.0
     */
    public function render(string $template, array $values): string
    {
        return $this->replaceTags($template, $values, []);
    }

    /**
     * Recursively replace the tags in an administrator authored text.
     *
     * @param   string    $text    The text to process.
     * @param   array     $values  The field values.
     * @param   string[]  $stack   Rule names currently being expanded (loop protection).
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function replaceTags(string $text, array $values, array $stack): string
    {
        if ($text === '' || \count($stack) > self::MAX_DEPTH) {
            return $text;
        }

        return (string) preg_replace_callback(
            self::TAG_PATTERN,
            function (array $match) use ($values, $stack): string {
                $name = strtolower($match[1]);

                // A rule wins over a plain field, unless we are already inside that very rule.
                if (isset($this->rules[$name]) && !\in_array($name, $stack, true)) {
                    return $this->renderRule($name, $values, $stack);
                }

                if (isset($values[$name])) {
                    return (string) $values[$name]['value'];
                }

                // Unknown tag: leave it untouched so the administrator can spot the typo.
                return $match[0];
            },
            $text
        );
    }

    /**
     * Render one rule.
     *
     * @param   string    $name    Lower case tag name of the rule.
     * @param   array     $values  The field values.
     * @param   string[]  $stack   Rule names currently being expanded.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function renderRule(string $name, array $values, array $stack): string
    {
        $rule = $this->rules[$name];

        if (!$this->isAuthorised($rule['access'])) {
            return '';
        }

        // A custom tag without a matching field always counts as "has data".
        $hasData = isset($values[$name]) ? (bool) $values[$name]['hasData'] : true;
        $html    = $hasData ? $rule['html'] : $rule['htmlNo'];

        $stack[] = $name;

        return $this->replaceTags($html, $values, $stack);
    }

    /**
     * Check whether the current visitor may see a rule.
     *
     * @param   int[]  $access  View levels allowed by the rule, empty means everybody.
     *
     * @return  bool
     *
     * @since   4.0.0
     */
    private function isAuthorised(array $access): bool
    {
        if ($access === []) {
            return true;
        }

        return array_intersect($access, $this->viewLevels) !== [];
    }
}
