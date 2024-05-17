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

namespace League\Csv\Query;

use ArrayAccess;
use League\Csv\StatementError;
use ReflectionException;
use ReflectionObject;

use function array_is_list;
use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function explode;
use function implode;
use function is_array;
use function is_int;
use function is_object;
use function lcfirst;
use function str_replace;

final class Select
{
    /**
     * Tries to retrieve the value from an array or an object.
     *
     * If the value is an array and the key is an integer the content will be retrieved
     * from the array_values array form. Negative offset are supported.
     * If the value is an object, the key MUST be a string.
     *
     * @throws ReflectionException
     * @throws StatementError If the value can not be retrieved
     */
    public static function one(mixed $value, string|int $key): mixed
    {
        $res = self::multiple($value, $key);

        return $res[$key];
    }

    /**
     * @throws ReflectionException
     *
     * @return non-empty-array<array-key, mixed>
     */
    public static function multiple(mixed $value, string|int ...$key): array
    {
        return match (true) {
            is_object($value) => self::getObjectPropertyValue($value, ...$key),
            is_array($value) => self::getArrayEntry($value, ...$key),
            default => throw new StatementError('The value must be an array or an object; received '.gettype($value).'.'),
        };
    }

    /**
     * @throws StatementError
     *
     * @return non-empty-array<array-key, mixed>
     */
    private static function getArrayEntry(array $value, string|int ...$keys): array
    {
        $res = [];
        $arrValues = array_values($value);
        foreach ($keys as $key) {
            if (array_key_exists($key, $res)) {
                continue;
            }
            $offset = $key;
            if (is_int($offset)) {
                if (!array_is_list($value)) {
                    $value = $arrValues;
                }

                if ($offset < 0) {
                    $offset += count($value);
                }
            }

            $res[$key] = array_key_exists($offset, $value) ? $value[$offset] : throw StatementError::dueToUnknownColumn($key, $value);
        }

        return [] !== $res ? $res : throw StatementError::dueToMissingColumn();
    }

    /**
     * @throws ReflectionException
     * @throws StatementError
     *
     * @return non-empty-array<array-key, mixed>
     */
    private static function getObjectPropertyValue(object $value, string|int ...$keys): array
    {
        $res = [];
        $refl = new ReflectionObject($value);
        foreach ($keys as $key) {
            if (array_key_exists($key, $res)) {
                continue;
            }

            if (is_int($key)) {
                throw StatementError::dueToUnknownColumn($key, $value);
            }

            if ($refl->hasProperty($key) && $refl->getProperty($key)->isPublic()) {
                $res[$key] = $refl->getProperty($key)->getValue($value);
                continue;
            }

            $methodNameList = [$key];
            if (($camelCasedKey = self::camelCase($key)) !== $key) {
                $methodNameList[] = $camelCasedKey;
            }
            $methodNameList[] = self::camelCase($key, 'get');
            foreach ($methodNameList as $methodName) {
                if ($refl->hasMethod($methodName)
                    && $refl->getMethod($methodName)->isPublic()
                    && 1 > $refl->getMethod($methodName)->getNumberOfRequiredParameters()
                ) {
                    $res[$key] = $refl->getMethod($methodName)->invoke($value);
                    continue 2;
                }
            }

            if (method_exists($value, '__call')) {
                $res[$key] = $refl->getMethod('__call')->invoke($value, $methodNameList[1]);
                continue;
            }

            if ($value instanceof ArrayAccess && $value->offsetExists($key)) {
                $res[$key] =  $value->offsetGet($key);
                continue;
            }

            throw StatementError::dueToUnknownColumn($key, $value);
        }

        return [] !== $res ? $res : throw StatementError::dueToMissingColumn();
    }

    private static function camelCase(string $value, string $prefix = ''): string
    {
        if ('' !== $prefix) {
            $prefix .= '_';
        }

        return lcfirst(implode('', array_map(
            ucfirst(...),
            explode(' ', str_replace(['-', '_'], ' ', $prefix.$value))
        )));
    }
}
