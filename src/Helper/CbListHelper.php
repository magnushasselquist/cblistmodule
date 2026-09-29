<?php

/**
 * @package     Community Builder list module
 * @copyright   Copyright (C) 2014 Magnus Hasselquist. All rights reserved.
 * @copyright   Copyright (C) 2021 Tazzios. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Hasselquist\Module\CbList\Site\Helper;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Helper for mod_cblistmodule.
 *
 * @since  4.0.0
 */
class CbListHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    /**
     * CB field types whose stored value is a key into #__comprofiler_field_values.
     *
     * @var    string[]
     * @since  4.0.0
     */
    private const LABELLED_TYPES = ['multicheckbox', 'multiselect', 'select', 'radio'];

    /**
     * Columns of #__users that must never be exposed through a tag.
     *
     * @var    string[]
     * @since  4.0.0
     */
    private const HIDDEN_COLUMNS = [
        'password',
        'params',
        'otpKey',
        'otep',
        'activation',
        'requireReset',
        'resetCount',
        'lastResetTime',
        'authProvider',
    ];

    /**
     * Constructor. The helper factory passes an (unused) config array.
     *
     * @param   array  $config  Configuration array.
     *
     * @since   4.0.0
     */
    public function __construct(array $config = [])
    {
    }

    /**
     * Render every user of the selected CB list.
     *
     * @param   Registry                 $params  The module parameters.
     * @param   CMSApplicationInterface  $app     The application.
     *
     * @return  array{users: array<int, array{id: int, html: string}>, debug: array{sql: string, messages: string[]}}
     *
     * @since   4.0.0
     */
    public function getUsers(Registry $params, CMSApplicationInterface $app): array
    {
        $db       = $this->getDatabase();
        $user     = $app->getIdentity();
        $messages = [];
        $sql      = '';

        // Debug output is only ever shown to Super Users, it contains SQL.
        $showDebug = (int) $params->get('debug', 0) === 1 && $user !== null && $user->authorise('core.admin');

        $result = static function (array $users) use (&$sql, &$messages, $showDebug): array {
            return [
                'users' => $users,
                'debug' => [
                    'sql'      => $showDebug ? $sql : '',
                    'messages' => $showDebug ? $messages : [],
                ],
            ];
        };

        $queryHelper = new CbListQueryHelper($db);
        $list        = $queryHelper->getList((int) $params->get('listid', 0));

        if ($list === null) {
            $messages[] = 'No published CB list found for the selected list id.';

            return $result([]);
        }

        $fields      = $this->getFields();
        $fieldTables = [];

        foreach ($fields as $field) {
            $fieldTables[strtolower($field['name'])] = $field['table'] === '#__users'
                ? CbListQueryHelper::ALIAS_USERS
                : CbListQueryHelper::ALIAS_COMPROFILER;
        }

        $query = $queryHelper->buildUserIdQuery(
            $list,
            $fieldTables,
            (string) $params->get('orderby', 'list_default'),
            (string) $params->get('sortorder', 'list_default'),
            (int) $params->get('user-limit', 10)
        );

        $sql      = (string) $query;
        $messages = array_merge($messages, $queryHelper->getMessages());
        $ids      = array_map('intval', $db->setQuery($query)->loadColumn() ?: []);

        if ($ids === []) {
            $messages[] = 'The list is empty.';

            return $result([]);
        }

        $persons  = $this->getPersons($ids);
        $labels   = $this->getFieldLabels($fields);
        $rules    = TemplateRenderer::normaliseRules($params->get('rules'));
        $renderer = new TemplateRenderer($rules, $user !== null ? $user->getAuthorisedViewLevels() : [1]);
        $template = (string) $params->get('template', '');
        $escape   = (int) $params->get('escape_values', 1) === 1;
        $users    = [];

        foreach ($ids as $id) {
            if (!isset($persons[$id])) {
                continue;
            }

            $values  = $this->buildValues($persons[$id], $fields, $labels, $escape);
            $users[] = [
                'id'   => $id,
                'html' => $renderer->render($template, $values),
            ];
        }

        return $result($users);
    }

    /**
     * Number of grid columns, or 'auto-fit'.
     *
     * @param   Registry  $params  The module parameters.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    public function getColumns(Registry $params): string
    {
        $columns = (int) $params->get('columns', 0);

        return $columns > 0 ? (string) min($columns, 12) : 'auto-fit';
    }

    /**
     * Minimum column width in rem.
     *
     * @param   Registry  $params  The module parameters.
     *
     * @return  float
     *
     * @since   4.0.0
     */
    public function getMinWidth(Registry $params): float
    {
        $width = $params->get('Minwidth', 5);

        return is_numeric($width) && (float) $width > 0 ? (float) $width : 5.0;
    }

    /**
     * Load the CB fields that can be used as tags.
     *
     * @return  array<int, array{fieldid: int, name: string, type: string, table: string}>
     *
     * @since   4.0.0
     */
    private function getFields(): array
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['f.fieldid', 'f.name', 'f.type', 'f.table']))
            ->from($db->quoteName('#__comprofiler_fields', 'f'))
            ->whereIn($db->quoteName('f.table'), ['#__users', '#__comprofiler'], ParameterType::STRING)
            ->whereNotIn($db->quoteName('f.name'), self::HIDDEN_COLUMNS, ParameterType::STRING)
            ->where($db->quoteName('f.tablecolumns') . ' <> ' . $db->quote(''));

        $rows = $db->setQuery($query)->loadAssocList() ?: [];

        foreach ($rows as &$row) {
            $row['fieldid'] = (int) $row['fieldid'];
            $row['name']    = (string) $row['name'];
            $row['type']    = (string) $row['type'];
            $row['table']   = (string) $row['table'];
        }

        return $rows;
    }

    /**
     * Load labels for select style fields, keyed by field id and stored value.
     *
     * @param   array  $fields  The CB fields.
     *
     * @return  array<int, array<string, string>>
     *
     * @since   4.0.0
     */
    private function getFieldLabels(array $fields): array
    {
        $fieldIds = [];

        foreach ($fields as $field) {
            if (\in_array($field['type'], self::LABELLED_TYPES, true)) {
                $fieldIds[] = $field['fieldid'];
            }
        }

        if ($fieldIds === []) {
            return [];
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['fieldid', 'fieldtitle', 'fieldlabel']))
            ->from($db->quoteName('#__comprofiler_field_values'))
            ->whereIn($db->quoteName('fieldid'), $fieldIds);

        $labels = [];

        foreach ($db->setQuery($query)->loadAssocList() ?: [] as $row) {
            $label = trim((string) $row['fieldlabel']);

            if ($label !== '') {
                $labels[(int) $row['fieldid']][(string) $row['fieldtitle']] = $label;
            }
        }

        return $labels;
    }

    /**
     * Load the user and CB profile rows for the given ids.
     *
     * @param   int[]  $ids  User ids.
     *
     * @return  array<int, array<string, mixed>>  Rows keyed by user id.
     *
     * @since   4.0.0
     */
    private function getPersons(array $ids): array
    {
        $db = $this->getDatabase();
        $u  = CbListQueryHelper::ALIAS_USERS;
        $ue = CbListQueryHelper::ALIAS_COMPROFILER;

        $query = $db->getQuery(true)
            ->select([$u . '.*', $ue . '.*'])
            ->from($db->quoteName('#__users', $u))
            ->join('INNER', $db->quoteName('#__comprofiler', $ue), $db->quoteName($ue . '.id') . ' = ' . $db->quoteName($u . '.id'))
            ->whereIn($db->quoteName($u . '.id'), $ids);

        $persons = [];

        foreach ($db->setQuery($query)->loadAssocList() ?: [] as $row) {
            foreach (self::HIDDEN_COLUMNS as $column) {
                unset($row[$column]);
            }

            $persons[(int) $row['id']] = $row;
        }

        return $persons;
    }

    /**
     * Build the tag values for one user.
     *
     * @param   array  $person  The user row.
     * @param   array  $fields  The CB fields.
     * @param   array  $labels  Field value labels.
     * @param   bool   $escape  Whether to HTML escape the values.
     *
     * @return  array<string, array{value: string, hasData: bool}>
     *
     * @since   4.0.0
     */
    private function buildValues(array $person, array $fields, array $labels, bool $escape): array
    {
        $values = [];
        $id     = (int) ($person['id'] ?? 0);

        foreach ($fields as $field) {
            $name    = $field['name'];
            $raw     = $person[$name] ?? null;
            $hasData = $raw !== null && (string) $raw !== '';
            $value   = (string) ($raw ?? '');

            if (\array_key_exists($name . 'approved', $person)) {
                // Image field: only show approved images.
                if (!$hasData || (int) $person[$name . 'approved'] !== 1) {
                    $hasData = false;
                    $value   = '';
                } else {
                    if ($name === 'canvas') {
                        // The default canvas images are stored with an incorrect path.
                        $value = str_ireplace('Gallery/', 'gallery/canvas/', $value);
                    }

                    $value = Uri::root() . 'images/comprofiler/' . ltrim($value, '/');
                }
            } elseif ($hasData && \in_array($field['type'], self::LABELLED_TYPES, true)) {
                $fieldLabels = $labels[$field['fieldid']] ?? [];
                $parts       = [];

                foreach (explode('|*|', $value) as $part) {
                    $parts[] = $fieldLabels[$part] ?? $part;
                }

                $value = implode(' ', $parts);
            }

            $values[strtolower($name)] = [
                'value'   => $escape ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8') : $value,
                'hasData' => $hasData,
            ];
        }

        $values['id']          = ['value' => (string) $id, 'hasData' => $id > 0];
        $values['user_id']     = $values['id'];
        $values['site_url']    = ['value' => Uri::root(), 'hasData' => true];
        $values['profile_url'] = [
            'value'   => Route::_('index.php?option=com_comprofiler&view=userprofile&user=' . $id),
            'hasData' => $id > 0,
        ];

        return $values;
    }
}
