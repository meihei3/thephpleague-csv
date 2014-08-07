<?php
/**
* This file is part of the League.csv library
*
* @license http://opensource.org/licenses/MIT
* @link https://github.com/thephpleague/csv/
* @version 5.5.0
* @package League.csv
*
* For the full copyright and license information, please view the LICENSE
* file that was distributed with this source code.
*/
namespace League\Csv\Iterator;

use CallbackFilterIterator;
use Iterator;

/**
 *  A Trait to filter Iterator against
 *  a collection of CallbackFilterIterator object
 *
 * @package League.csv
 * @since  4.2.1
 *
 */
trait Filter
{
    /**
     * Callable function to filter the iterator
     *
     * @var array
     */
    protected $iterator_filters = [];

    /**
     * DEPRECATION WARNING! This method will be removed in the next major point release
     *
     * @deprecated deprecated since version 5.1
     *
     * @param callable $callable
     *
     * @return static The invoked object
     */
    public function setFilter(callable $callable)
    {
        return $this->addFilter($callable);
    }

    /**
     * Set the Iterator filter method
     *
     * @param callable $callable
     *
     * @return static The invoked object
     */
    public function addFilter(callable $callable)
    {
        $this->iterator_filters[] = $callable;

        return $this;
    }

    /**
     * Remove a filter from the callable collection
     *
     * @param callable $callable
     *
     * @return static The invoked object
     */
    public function removeFilter(callable $callable)
    {
        $res = array_search($callable, $this->iterator_filters, true);
        if (false !== $res) {
            unset($this->iterator_filters[$res]);
        }

        return $this;
    }

    /**
     * Detect if the callable filter is already registered
     *
     * @param callable $callable
     *
     * @return boolean
     */
    public function hasFilter(callable $callable)
    {
        return false !== array_search($callable, $this->iterator_filters, true);
    }

    /**
     * Remove all registered callable filter
     *
     * @return static The invoked object
     */
    public function clearFilter()
    {
        $this->iterator_filters = [];

        return $this;
    }

    /**
    * Filter the Iterator
    *
    * @param \Iterator $iterator
    *
    * @return \Iterator
    */
    protected function applyIteratorFilter(Iterator $iterator)
    {
        foreach ($this->iterator_filters as $callable) {
            $iterator = new CallbackFilterIterator($iterator, $callable);
        }
        $this->clearFilter();

        return $iterator;
    }
}