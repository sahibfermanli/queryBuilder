<?php

namespace App\Services;

use App\Core\Database;
use Closure;
use PDO;
use PDOStatement;

class DB
{
    private string $table;
    private string $select = '*';
    private array $order_by = [];
    private array $group_by = [];
    private array $join = [];
    private array $where = [];
    private array $or_where = [];
    private array $bindings = [];
    private string $limit = '';

    private array $onSqlArr = [];


    public function __construct($table) {
        $this->table = $table;
    }

    public function getDatabase(): Database
    {
        return new Database();
    }

    public function getBindings(): array
    {
        return $this->bindings;
    }

    public static function query(): DB
    {
        return new self('');
    }

    public static function table(string $table): DB
    {
        return new self($table);
    }

    public function select(string|array $columns = '*'): static
    {
        if (is_array($columns)) {
            $this->select = implode(', ', $columns);
        } else {
            $this->select = $columns;
        }

        return $this;
    }

    public function groupBy(string|array $column): static
    {
        if (is_array($column)) {
            foreach ($column as $col) {
                $this->group_by[] = $col;
            }
        } else {
            $this->group_by[] = $column;
        }

        return $this;
    }

    public function orderBy($column, $direction = 'ASC'): static
    {
        $this->order_by[] = "$column $direction";

        return $this;
    }

    public function limit($count, $offset = 0): static
    {
        $this->limit = "LIMIT $offset, $count";

        return $this;
    }

    private function baseJoin($type, $table, $first, $operator = null, $second = null): void
    {
        if (!is_null($table)) {
            if ($first instanceof Closure) {
                $sub = new static('');
                $first($sub);

                if (count($sub->getOnSqlArr())) {
                    $onSql = implode(' and ', $sub->getOnSqlArr());

                    $sql = "$type $table ON $onSql";

                    $onWhereSql = $sub->getWhereQuery(false, true, true);

                    if ($onWhereSql) {
                        $sql .= $onWhereSql;
                    }

                    $this->join[] = [
                        'sql' => $sql,
                        'binding' => $sub->getBindings(),
                    ];
                }
            } else {
                $this->join[] = [
                    'sql' => "$type $table ON $first $operator $second",
                ];
            }
        }
    }

    private function getOnSqlArr(): array
    {
        return $this->onSqlArr;
    }

    public function on($first, $operator, $second): void
    {
        $this->onSqlArr[] = "$first $operator $second";
    }

    public function join($table, $first, $operator = null, $second = null): static
    {
        $this->baseJoin('join', $table, $first, $operator, $second);

        return $this;
    }

    public function leftJoin($table, $first, $operator = null, $second = null): static
    {
        $this->baseJoin('left join', $table, $first, $operator, $second);

        return $this;
    }

    public function rightJoin($table, $first, $operator = null, $second = null): static
    {
        $this->baseJoin('right join', $table, $first, $operator, $second);

        return $this;
    }

    public function innerJoin($table, $first, $operator = null, $second = null): static
    {
        $this->baseJoin('inner join', $table, $first, $operator, $second);

        return $this;
    }

    public function where($column, $operator = null, $value = null): static
    {
        if (!is_null($column)) {
            if ($column instanceof Closure) {
                $sub_where = new static('');
                $column($sub_where);
                $this->where[] = [
                    'sql' => '(' . $sub_where->getWhereQuery(false, true) . ')',
                    'binding' => $sub_where->getBindings(),
                ];
            } elseif ($operator === null && $value === null) {
                $this->whereNull($column);
            } elseif ($operator !== null && $value === null) {
                $this->where[] = [
                    'sql' => "$column = ?",
                    'binding' => $operator,
                ];
            } elseif ($operator !== null && $value !== null) {
                $this->where[] = [
                    'sql' => "$column $operator ?",
                    'binding' => $value,
                ];
            }
        }

        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): static
    {
        if (!is_null($column)) {
            if ($column instanceof Closure) {
                $sub_where = new static('');
                $column($sub_where);
                $this->or_where[] = [
                    'sql' => '(' . $sub_where->getWhereQuery(false, true) . ')',
                    'binding' => $sub_where->getBindings(),
                ];
            } elseif ($operator === null && $value === null) {
                $this->orWhereNull($column);
            } elseif ($operator !== null && $value === null) {
                $this->or_where[] = [
                    'sql' => "$column = ?",
                    'binding' => $operator,
                ];
            } elseif ($operator !== null && $value !== null) {
                $this->or_where[] = [
                    'sql' => "$column $operator ?",
                    'binding' => $value,
                ];
            }
        }

        return $this;
    }

    public function whereNull($column): static
    {
        $this->where[] = [
            'sql' => "$column is null",
        ];

        return $this;
    }

    public function whereNotNull($column): static
    {
        $this->where[] = [
            'sql' => "$column is not null",
        ];

        return $this;
    }

    public function orWhereNull($column): static
    {
        $this->or_where[] = [
            'sql' => "$column is null",
        ];

        return $this;
    }

    public function orWhereNotNull($column): static
    {
        $this->or_where[] = [
            'sql' => "$column is not null",
        ];

        return $this;
    }

    private function setBindingsForQuery(array $bindings, &$sql): void
    {
        foreach ($bindings as $value) {
            if (is_array($value)) {
                $this->setBindingsForQuery($value, $sql);
                continue;
            }

            $value = str_replace("'", "\'", $value);
            $value = !is_numeric($value) ? "'$value'" : $value;
            $search = '/'.preg_quote('?', '/').'/';
            $sql = preg_replace($search, $value, $sql, 1);
        }
    }

    private function getWhereQuery($addBindings = true, $onlyConditions = false, $isJoinWhere = false): string
    {
        $sql = '';
        $has_where = false;

        // where
        $where_arr = [];
        $where_bindings = [];
        foreach ($this->where as $where) {
            $where_arr[] = $where['sql'];
            if (isset($where['binding'])) {
                $where_bindings[] = $where['binding'];
                $this->bindings[] = $where['binding'];
            }
        }

        if (count($where_arr)) {
            $where_base = $isJoinWhere ? " AND " : "";
            $where_sql = $where_base . implode(' AND ', $where_arr);

            if ($addBindings) {
                $this->setBindingsForQuery($where_bindings, $where_sql);
            }

            $sql .= $where_sql;
            $has_where = true;
        }

        // orWhere
        $or_where_arr = [];
        $or_where_bindings = [];
        foreach ($this->or_where as $where) {
            $or_where_arr[] = $where['sql'];
            if (isset($where['binding'])) {
                $or_where_bindings[] = $where['binding'];
                $this->bindings[] = $where['binding'];
            }
        }

        if (count($or_where_arr)) {
            $or_where_base = ($has_where || $isJoinWhere) ? " OR " : "";
            $or_where_sql = $or_where_base . implode(' OR ', $or_where_arr);

            if ($addBindings) {
                $this->setBindingsForQuery($or_where_bindings, $or_where_sql);
            }

            $sql .= $or_where_sql;
            $has_where = true;
        }

        if ($has_where && !$onlyConditions) {
            $sql = " WHERE " . $sql;
        }

        return $sql;
    }

    private function getJoinQuery($addBindings = true, $onlyConditions = false): string
    {
        $sql = '';
        $has_join = false;

        // join
        $join_arr = [];
        $join_bindings = [];
        foreach ($this->join as $join) {
            $join_arr[] = $join['sql'];
            if (isset($join['binding'])) {
                $join_bindings[] = $join['binding'];
                $this->bindings[] = $join['binding'];
            }
        }

        if (count($join_arr)) {
            $join_sql = implode(' ', $join_arr);

            if ($addBindings) {
                $this->setBindingsForQuery($join_bindings, $join_sql);
            }

            $sql .= $join_sql;
            $has_join = true;
        }

        if ($has_join && !$onlyConditions) {
            $sql = " " . $sql;
        }

        return $sql;
    }


    public function toSql($addBindings = true): string
    {
        // select
        $sql = "SELECT " . $this->select . " FROM " . $this->table;

        // join
        $sql .= $this->getJoinQuery($addBindings);

        // where
        $sql .= $this->getWhereQuery($addBindings);

        // groupBy
        if (count($this->group_by)) {
            $group_by = implode(', ', $this->group_by);
            $sql .= " GROUP BY $group_by";
        }

        // orderBy
        if (count($this->order_by)) {
            $order_by = implode(', ', $this->order_by);
            $sql .= " ORDER BY $order_by";
        }

        // limit
        if (!empty($this->limit)) {
            $sql .= " $this->limit";
        }

        return $sql;
    }

    public function dd(): array
    {
        return [
            'query' => $this->toSql(false),
            'bindings' => $this->bindings
        ];
    }

    private function execute(): false|PDOStatement
    {
        $stmt = $this->getDatabase()->getPDO()->prepare($this->toSql(false));

        $this->setBindings($this->bindings, $stmt);

        $stmt->execute();

        return $stmt;
    }

    private function setBindings(array $bindings, &$stmt, &$i = 0): void
    {
        foreach ($bindings as $index => $value) {
            if (is_array($value)) {
                $this->setBindings($value, $stmt, $i);
                continue;
            }

            $i++;
            $stmt->bindValue($i, $value);
        }
    }


    public function get(): false|array
    {
        return $this->execute()->fetchAll(PDO::FETCH_ASSOC);
    }

    public function first(): false|array
    {
        return $this->execute()->fetch(PDO::FETCH_ASSOC);
    }

    public function last(): array
    {
        $data = $this->get();

        return is_array($data) ? end($data) : [];
    }
}