<?php

declare(strict_types=1);

namespace RafikiDB;

/**
 * Join specification for the data builder (column-based, no SQL).
 */
final class JoinSpec
{
    public function __construct(
        public readonly string $table,
        public readonly string $type = 'LEFT JOIN',
        public readonly string $fromColumn = '',
        public readonly string $toColumn = '',
        public readonly ?string $on = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'table' => $this->table,
            'from_column' => $this->fromColumn,
            'to_column' => $this->toColumn,
            'on' => $this->on ?? '',
        ];
    }
}

/**
 * Fluent typed data builder.
 *
 * @template T
 */
final class DataBuilder
{
    /** @var array<int, array{column: string, op: string, value: mixed, values: array}> */
    private array $filters = [];
    private ?string $columns = null;
    /** @var string[] */
    private array $order = [];
    private ?int $limit = null;
    private ?int $offset = null;
    private ?string $cursor = null;
    /** @var JoinSpec[] */
    private array $joins = [];

    public function __construct(
        private readonly Client $client,
        private readonly string $table,
    ) {
    }

    public function select(?string $columns = null): self
    {
        $this->columns = $columns;
        return $this;
    }

    public function limit(int $count): self
    {
        $this->limit = $count;
        return $this;
    }

    public function offset(int $start): self
    {
        $this->offset = $start;
        return $this;
    }

    public function cursor(string $id): self
    {
        $this->cursor = $id;
        return $this;
    }

    public function order(string $column, bool $ascending = true): self
    {
        $this->order[] = $column . '.' . ($ascending ? 'asc' : 'desc');
        return $this;
    }

    public function eq(string $column, mixed $value): self
    {
        return $this->filter($column, 'eq', $value);
    }

    public function neq(string $column, mixed $value): self
    {
        return $this->filter($column, 'neq', $value);
    }

    public function gt(string $column, mixed $value): self
    {
        return $this->filter($column, 'gt', $value);
    }

    public function gte(string $column, mixed $value): self
    {
        return $this->filter($column, 'gte', $value);
    }

    public function lt(string $column, mixed $value): self
    {
        return $this->filter($column, 'lt', $value);
    }

    public function lte(string $column, mixed $value): self
    {
        return $this->filter($column, 'lte', $value);
    }

    public function like(string $column, string $pattern): self
    {
        return $this->filter($column, 'like', $pattern);
    }

    public function ilike(string $column, string $pattern): self
    {
        return $this->filter($column, 'ilike', $pattern);
    }

    public function isNull(string $column): self
    {
        return $this->filter($column, 'is', null);
    }

    public function isNotNull(string $column): self
    {
        return $this->filter($column, 'is', 'not.null');
    }

    /** @param array<mixed> $values */
    public function in(string $column, array $values): self
    {
        return $this->filter($column, 'in', null, $values);
    }

    public function join(JoinSpec $spec): self
    {
        if ($spec->table === '') {
            throw new \InvalidArgumentException('join() requires a table');
        }
        if ($spec->on === null && ($spec->fromColumn === '' || $spec->toColumn === '')) {
            throw new \InvalidArgumentException('join() requires fromColumn + toColumn, or a raw on clause');
        }
        $this->joins[] = $spec;
        return $this;
    }

    /** @return Envelope<array<int, array<string, mixed>>> */
    public function execute(): Envelope
    {
        if ($this->joins !== []) {
            return $this->queryEngine();
        }

        $params = [];
        if ($this->columns !== null) {
            $params['select'] = $this->columns;
        }
        if ($this->order !== []) {
            $params['order'] = implode(',', $this->order);
        }
        if ($this->limit !== null) {
            $params['limit'] = (string) $this->limit;
        }
        if ($this->cursor !== null) {
            $params['cursor'] = $this->cursor;
        } elseif ($this->offset !== null) {
            $params['offset'] = (string) $this->offset;
        }
        foreach ($this->filters as $f) {
            $params[$f['column']] = match ($f['op']) {
                'in' => 'in.(' . implode(',', array_map('strval', $f['values'])) . ')',
                'is' => $f['value'] === null ? 'is.null' : 'is.not.null',
                default => $f['op'] . '.' . (string) $f['value'],
            };
        }

        return $this->client->get('/data/' . $this->table, $params);
    }

    /** @return Envelope<array<int, array<string, mixed>>> */
    private function queryEngine(): Envelope
    {
        $config = [
            'columns' => $this->columns !== null
                ? array_values(array_filter(array_map('trim', explode(',', $this->columns))))
                : [],
            'filters' => array_map(fn (array $f): array => [
                'column' => $f['column'],
                'operator' => $f['op'] === 'is'
                    ? ($f['value'] === null ? 'is_null' : 'is_not_null')
                    : $f['op'],
                'value' => $f['op'] === 'in' || $f['op'] === 'is' ? '' : (string) $f['value'],
                'values' => array_map('strval', $f['values']),
            ], $this->filters),
            'sort' => array_map(fn (string $o): array => [
                'column' => explode('.', $o)[0],
                'direction' => explode('.', $o)[1] ?? 'asc',
            ], $this->order),
            'limit' => $this->limit ?? 50,
            'offset' => $this->offset ?? 0,
            'joins' => array_map(fn (JoinSpec $j): array => $j->toArray(), $this->joins),
        ];

        $env = $this->client->post('/projects/' . $this->client->projectId() . '/query/run', [
            'table_slug' => $this->table,
            'config' => $config,
        ]);
        $rows = is_array($env->data) ? ($env->data['rows'] ?? []) : [];

        return new Envelope($env->success, $env->message, $rows, $env->errors);
    }

    /** @return Envelope<array<string, mixed>> */
    public function get(string $id): Envelope
    {
        return $this->client->get('/data/' . $this->table . '/' . $id);
    }

    /** @return Envelope<array<string, mixed>|null> */
    public function single(): Envelope
    {
        $this->limit(1);
        $env = $this->execute();
        $data = isset($env->data[0]) ? $env->data[0] : null;
        return new Envelope($env->success, $env->message, $data, $env->errors);
    }

    /** @return Envelope<array{count: int}> */
    public function head(): Envelope
    {
        return $this->client->get('/data/' . $this->table, ['count' => 'exact']);
    }

    /** @param array<string, mixed>|list<array<string, mixed>> $rows */
    public function insert(array $rows): Envelope
    {
        return $this->client->post('/data/' . $this->table, $rows);
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): Envelope
    {
        $id = $this->idFromFilters();
        if ($id === null) {
            throw new \InvalidArgumentException('update() requires a primary-key filter: eq("id", "...")');
        }
        return $this->client->patch('/data/' . $this->table . '/' . $id, $values);
    }

    public function delete(): Envelope
    {
        $id = $this->idFromFilters();
        if ($id === null) {
            throw new \InvalidArgumentException('delete() requires a primary-key filter: eq("id", "...")');
        }
        return $this->client->delete('/data/' . $this->table . '/' . $id);
    }

    /** @param array<mixed> $values */
    private function filter(string $column, string $op, mixed $value = null, array $values = []): self
    {
        $this->filters[] = ['column' => $column, 'op' => $op, 'value' => $value, 'values' => $values];
        return $this;
    }

    private function idFromFilters(): ?string
    {
        foreach ($this->filters as $f) {
            if ($f['column'] === 'id' && $f['op'] === 'eq' && $f['value'] !== null) {
                return (string) $f['value'];
            }
        }
        return null;
    }
}