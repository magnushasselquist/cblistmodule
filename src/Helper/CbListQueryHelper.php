<?php

/**
 * @package     Community Builder list module
 * @copyright   Copyright (C) 2014 Magnus Hasselquist. All rights reserved.
 * @copyright   Copyright (C) 2021 Tazzios. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

declare(strict_types=1);

namespace Hasselquist\Module\CbList\Site\Helper;

use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Database\QueryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds the user id query for a Community Builder list the same way CB does it.
 *
 * Kept separate from the module helper so it can be reused by other extensions.
 *
 * @since  4.0.0
 */
final class CbListQueryHelper
{
    /**
     * Table aliases used in the generated query.
     *
     * @var    string
     * @since  4.0.0
     */
    public const ALIAS_USERS = 'u';

    /**
     * @var    string
     * @since  4.0.0
     */
    public const ALIAS_COMPROFILER = 'ue';

    /**
     * The database driver.
     *
     * @var    DatabaseInterface
     * @since  4.0.0
     */
    private DatabaseInterface $db;

    /**
     * Debug messages collected while building the query.
     *
     * @var    string[]
     * @since  4.0.0
     */
    private array $messages = [];

    /**
     * Constructor.
     *
     * @param   DatabaseInterface  $db  The database driver.
     *
     * @since   4.0.0
     */
    public function __construct(DatabaseInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Debug messages collected while building the query.
     *
     * @return  string[]
     *
     * @since   4.0.0
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Load a published CB list.
     *
     * @param   int  $listId  The CB list id.
     *
     * @return  array|null  Array with 'params' (decoded JSON) and 'usergroupids' (int[]), or null when not found.
     *
     * @since   4.0.0
     */
    public function getList(int $listId): ?array
    {
        if ($listId <= 0) {
            return null;
        }

        $db    = $this->db;
        $query = $db->getQuery(true)
            ->select($db->quoteName(['params', 'usergroupids']))
            ->from($db->quoteName('#__comprofiler_lists'))
            ->where($db->quoteName('listid') . ' = :listid')
            ->where($db->quoteName('published') . ' = 1')
            ->bind(':listid', $listId, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadAssoc();

        if (!$row) {
            return null;
        }

        $params = json_decode((string) $row['params'], true);

        if (!\is_array($params)) {
            $params = [];
        }

        $groups = array_values(array_filter(array_map(
            'intval',
            explode('|*|', str_replace(',', '|*|', (string) $row['usergroupids']))
        )));

        return [
            'params'       => $params,
            'usergroupids' => $groups,
        ];
    }

    /**
     * Build the query returning the ids of the users in the list.
     *
     * @param   array        $list         The list as returned by getList().
     * @param   array        $fieldTables  Map of lower case field name => table alias ('u' or 'ue').
     * @param   string|null  $orderBy      Field name to order by, null or 'list_default' for the list default.
     * @param   string       $direction    'asc', 'desc', 'random' or '' for the list default.
     * @param   int          $limit        Maximum number of users, 0 for no limit.
     *
     * @return  QueryInterface
     *
     * @since   4.0.0
     */
    public function buildUserIdQuery(array $list, array $fieldTables, ?string $orderBy, string $direction, int $limit): QueryInterface
    {
        $db     = $this->db;
        $params = $list['params'];
        $u      = self::ALIAS_USERS;
        $ue     = self::ALIAS_COMPROFILER;

        $query = $db->getQuery(true)
            ->select($db->quoteName($ue . '.id'))
            ->from($db->quoteName('#__users', $u))
            ->join(
                'INNER',
                $db->quoteName('#__comprofiler', $ue),
                $db->quoteName($ue . '.id') . ' = ' . $db->quoteName($u . '.id')
            );

        // Group membership. A list without user groups shows nobody, as in CB itself.
        $groups = array_values(array_filter(array_map('intval', $list['usergroupids'] ?? [])));

        if ($groups === []) {
            $this->messages[] = 'The CB list has no user groups selected, so it cannot contain any users.';
            $query->where('1 = 0');

            return $query;
        }

        $query->where(
            'EXISTS (SELECT 1 FROM ' . $db->quoteName('#__user_usergroup_map', 'g')
            . ' WHERE ' . $db->quoteName('g.user_id') . ' = ' . $db->quoteName($u . '.id')
            . ' AND ' . $db->quoteName('g.group_id') . ' IN (' . implode(',', $groups) . '))'
        );

        if ((int) ($params['list_show_blocked'] ?? 0) === 0) {
            $query->where($db->quoteName($u . '.block') . ' = 0');
        }

        if ((int) ($params['list_show_unapproved'] ?? 0) === 0) {
            $query->where($db->quoteName($ue . '.approved') . ' = 1');
        }

        if ((int) ($params['list_show_unconfirmed'] ?? 0) === 0) {
            $query->where($db->quoteName($ue . '.confirmed') . ' = 1');
        }

        // CB list filters.
        $filterSql = $this->buildFilterSql($params, $fieldTables);

        if ($filterSql !== '') {
            $query->where('(' . $filterSql . ')');
        }

        // Ordering.
        $order = $this->buildOrder($params, $fieldTables, $orderBy, $direction);

        if ($order !== '') {
            $query->order($order);
        }

        if ($limit > 0) {
            $query->setLimit($limit);
        }

        return $query;
    }

    /**
     * Build the WHERE fragment for the list filters.
     *
     * @param   array  $params       Decoded list params.
     * @param   array  $fieldTables  Map of lower case field name => table alias.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function buildFilterSql(array $params, array $fieldTables): string
    {
        $mode = (int) ($params['filter_mode'] ?? 0);

        // Advanced mode: the CB administrator wrote raw SQL, exactly as CB itself executes it.
        if ($mode === 1) {
            return trim((string) ($params['filter_advanced'] ?? ''));
        }

        $filters = $params['filter_basic'] ?? [];

        if (!\is_array($filters)) {
            return '';
        }

        $parts = [];

        foreach ($filters as $filter) {
            if (!\is_array($filter)) {
                continue;
            }

            $column = trim((string) ($filter['column'] ?? ''));

            if ($column === '') {
                continue;
            }

            $quotedColumn = $this->quoteColumn($column, $fieldTables);

            if ($quotedColumn === null) {
                $this->messages[] = sprintf('Filter column "%s" is not a valid column name and was skipped.', $column);
                continue;
            }

            $part = $this->buildCondition(
                $quotedColumn,
                trim((string) ($filter['operator'] ?? '=')),
                (string) ($filter['value'] ?? '')
            );

            if ($part === null) {
                $this->messages[] = sprintf('Filter operator "%s" is not supported and was skipped.', (string) ($filter['operator'] ?? ''));
                continue;
            }

            $parts[] = $part;
        }

        return implode(' AND ', $parts);
    }

    /**
     * Build one filter condition.
     *
     * @param   string  $column    Quoted column name.
     * @param   string  $operator  CB operator, for example "=", "LIKE" or "<>||ISNULL".
     * @param   string  $value     Raw filter value.
     *
     * @return  string|null  The condition or null when the operator is unknown.
     *
     * @since   4.0.0
     */
    private function buildCondition(string $column, string $operator, string $value): ?string
    {
        $db     = $this->db;
        $orNull = false;

        if (str_ends_with($operator, '||ISNULL')) {
            $orNull   = true;
            $operator = substr($operator, 0, -\strlen('||ISNULL'));
        }

        $operator = strtoupper(trim($operator));

        switch ($operator) {
            case '=':
            case '<>':
            case '!=':
            case '>':
            case '<':
            case '>=':
            case '<=':
            case 'REGEXP':
            case 'NOT REGEXP':
            case 'RLIKE':
            case 'NOT RLIKE':
                $sql = $column . ' ' . $operator . ' ' . $db->quote($value);
                break;

            case 'LIKE':
            case 'NOT LIKE':
                $sql = $column . ' ' . $operator . ' ' . $db->quote('%' . $this->escapeLike($value) . '%');
                break;

            case 'LIKE%':
            case 'NOT LIKE%':
                $sql = $column . ' ' . substr($operator, 0, -1) . ' ' . $db->quote($this->escapeLike($value) . '%');
                break;

            case '%LIKE':
            case 'NOT %LIKE':
                $sql = $column . ' ' . str_replace('%', '', $operator) . ' ' . $db->quote('%' . $this->escapeLike($value));
                break;

            case 'IN':
            case 'NOT IN':
                $values = array_map('trim', explode(',', $value));
                $values = array_map(static fn (string $v): string => trim($v, '\'"'), $values);
                $values = array_map([$db, 'quote'], $values);
                $sql    = $column . ' ' . $operator . ' (' . implode(', ', $values) . ')';
                break;

            case 'IS NULL':
            case 'IS NOT NULL':
                $sql = $column . ' ' . $operator;
                break;

            default:
                return null;
        }

        if ($orNull) {
            return '(' . $sql . ' OR ' . $column . ' IS NULL)';
        }

        return '(' . $sql . ')';
    }

    /**
     * Escape LIKE wildcards in a value.
     *
     * @param   string  $value  The value.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\\%', '\\_'], trim($value, '\'"'));
    }

    /**
     * Build the ORDER BY expression.
     *
     * @param   array        $params       Decoded list params.
     * @param   array        $fieldTables  Map of lower case field name => table alias.
     * @param   string|null  $orderBy      Requested order field.
     * @param   string       $direction    Requested direction.
     *
     * @return  string
     *
     * @since   4.0.0
     */
    private function buildOrder(array $params, array $fieldTables, ?string $orderBy, string $direction): string
    {
        $direction = strtolower(trim($direction));

        if ($direction === 'random') {
            return 'RAND()';
        }

        $listSort      = $params['sort_basic'][0] ?? [];
        $listColumn    = trim((string) ($listSort['column'] ?? ''));
        $listDirection = strtoupper(trim((string) ($listSort['direction'] ?? 'ASC')));

        if ($orderBy === null || $orderBy === '' || $orderBy === 'list_default') {
            $orderBy = $listColumn;
        }

        if ($orderBy === '') {
            return '';
        }

        $column = $this->quoteColumn($orderBy, $fieldTables);

        if ($column === null) {
            $this->messages[] = sprintf('Order column "%s" is not a valid column name and was ignored.', $orderBy);

            return '';
        }

        if ($direction !== 'asc' && $direction !== 'desc') {
            $direction = $listDirection === 'DESC' ? 'desc' : 'asc';
        }

        return $column . ' ' . strtoupper($direction);
    }

    /**
     * Quote a column name, qualifying it with the table alias when the column is a known CB field.
     *
     * @param   string  $column       The column name from the list configuration.
     * @param   array   $fieldTables  Map of lower case field name => table alias.
     *
     * @return  string|null  Quoted column or null when the name is not a plain identifier.
     *
     * @since   4.0.0
     */
    private function quoteColumn(string $column, array $fieldTables): ?string
    {
        $column = trim($column, '` ');

        if (!preg_match('/^[A-Za-z0-9_]+(\.[A-Za-z0-9_]+)?$/', $column)) {
            return null;
        }

        $key = strtolower($column);

        if (isset($fieldTables[$key])) {
            return $this->db->quoteName($fieldTables[$key] . '.' . $column);
        }

        return $this->db->quoteName($column);
    }
}
