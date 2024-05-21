<?php

/**
 * League.Csv (https://csv.thephpleague.com)
 *
 * (c) Ignace Nyamagana Butera <nyamsprod@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace League\Csv\Query\Constraint;

use ArrayIterator;
use CallbackFilterIterator;
use Iterator;
use IteratorIterator;
use League\Csv\Query\Predicate;
use League\Csv\Query\Row;
use League\Csv\Query\QueryException;
use ReflectionException;
use Traversable;

use function array_filter;
use function is_array;
use function is_int;
use function is_string;

use const ARRAY_FILTER_USE_BOTH;

/**
 * Enable filtering a record by comparing the values of two of its column.
 *
 * When used with PHP's array_filter with the ARRAY_FILTER_USE_BOTH flag
 * the record offset WILL NOT BE taken into account
 */
final class TwoColumns implements Predicate
{
    /**
     * @throws QueryException
     */
    private function __construct(
        public readonly string|int $first,
        public readonly Comparison $operator,
        public readonly array|string|int $second,
    ) {
        if (is_array($this->second)) {
            $res = array_filter($this->second, fn (mixed $value): bool => !is_string($value) && !is_int($value));
            if ([] !== $res) {
                throw new QueryException('The second column must be a string, an integer or a list of strings and/or integer.');
            }
        }
    }

    /**
     * @throws QueryException
     */
    public static function filterOn(
        string|int $firstColumn,
        Comparison|string $operator,
        array|string|int $secondColumn
    ): self {
        if (!$operator instanceof Comparison) {
            $operator = Comparison::fromOperator($operator);
        }

        return new self($firstColumn, $operator, $secondColumn);
    }

    /**
     * @throws QueryException
     * @throws ReflectionException
     */
    public function __invoke(mixed $value, int|string $key): bool
    {
        $val = match (true) {
            is_array($this->second) => array_values(Row::from($value)->select(...$this->second)),
            default => Row::from($value)->value($this->second),
        };

        return Column::filterOn($this->first, $this->operator, $val)($value, $key);
    }

    public function filter(iterable $value): Iterator
    {
        return new CallbackFilterIterator(match (true) {
            $value instanceof Iterator => $value,
            $value instanceof Traversable => new IteratorIterator($value),
            default => new ArrayIterator($value),
        }, $this);
    }

    public function filterArray(iterable $value): array
    {
        return array_filter(
            !is_array($value) ? iterator_to_array($value) : $value,
            $this,
            ARRAY_FILTER_USE_BOTH
        );
    }
}
