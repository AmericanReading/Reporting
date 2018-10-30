<?php

namespace AmericanReading\Reporting;

use Exception;

abstract class ReportBase
{
    /**
     * Array of column descriptions. The keys of this array correspond to the
     * keys used to indicate columns in $dataSource. The values of these items
     * are associative arrays describing the column, including the string to
     * use for the column heading and the integer index of the column.
     *
     * @var array
     */
    protected $columns;

    /**
     * Array of associative arrays representing the data for the spreadsheet.
     * Each array item represents one row. Keys in row arrays represent columns.
     *
     * @var array
     */
    protected $data;

    /**
     * Object of options from the reportConfiguration
     *
     * @var object
     */
    protected $options;

    /**
     * Comparison function for sorting the data. The two parameters will be
     * StdClass objects representing rows of the data.
     *
     * See the static helper method makeSortFunction for assistance on building
     * functions using the columns keys.
     *
     * @var array
     */
    protected $sortFunction;

    /**
     * Descriptive name for the report.
     *
     * @var string
     */
    public $title;

    // TODO Remove after testing.
    public function dump()
    {
        print '<pre>';
        var_dump($this->columns);
        var_dump($this->data);
        print '</pre>';
    }

    // ------------------------------------------------------------------------

    /**
     * Build a new report, optionally providing a configuration as JSON or
     * as an object. The configuration may contain the members columns and data
     *
     * @param mixed $reportConfiguration
     * @throws Exception
     */
    public function __construct($reportConfiguration = null)
    {
        // String: try to decode as JSON.
        if (is_string($reportConfiguration)) {
            $reportConfiguration = json_decode($reportConfiguration);
        }

        // Array: use as data array or case as StdClass.
        if (is_array($reportConfiguration)) {
            if (self::isAssoc($reportConfiguration)) {
                // Cast associative arrays as objects.
                $reportConfiguration = (object) $reportConfiguration;
            } else {
                // Assume a numeric array is the data array.
                $this->setData($reportConfiguration);
                return;
            }
        }

        if (!is_object($reportConfiguration)) {
            return;
        }

        // Columns
        if (isset($reportConfiguration->columns)) {
            $this->setColumns($reportConfiguration->columns);
        }

        // Options
        if (isset($reportConfiguration->options)) {
            $this->setOptions($reportConfiguration->options);
        }

        // Data. Set the data last. Calling setData() will cause the instance
        // to determine the columns from the data if columns is not set.
        if (isset($reportConfiguration->data)) {
            $this->setData($reportConfiguration->data);
        }

    }

    // ------------------------------------------------------------------------

    /**
     * Provide a new array describing the columns.
     *
     * @param array $columns
     */
    public function setColumns($columns)
    {
        $this->columns = $columns;
        $this->restructureColumns();
        $this->sortColumns();
    }

    /**
     * Provide a new data array. If the columns member is not already set,
     * the instance will construct one from the info in data.
     *
     * @param array $data
     * @throws Exception
     */
    public function setData($data)
    {
        $this->data = $data;

        if (!isset($this->columns)) {
            $this->determineColumnsFromData();
        }

        $this->restructureData();
        $this->sortData();
    }

    public function setOptions($options)
    {
        // Associative array: cast as StdClass.
        if (is_array($options) && self::isAssoc($options)) {
            $options = (object) $options;
        }

        if (!is_object($options)) {
            throw new Exception('Options must be an object or associative array.');
        }

        // Store the new options member.
        $this->options = $options;

        if (isset($options->title)) {
            $this->title = $options->title;
        }

        // Call methods that require options to be set.
        $this->buildSortFunction();
    }

    // ------------------------------------------------------------------------

    /**
     * Determine the columns from the data.
     */
    protected function determineColumnsFromData()
    {
        if (!isset($this->data)) {
            return;
        }

        // Add the keys from each row into one large array.
        $allKeys = array();

        foreach ($this->data as $dataRow) {

            if (is_object($dataRow)) {
                $dataRow = get_object_vars($dataRow);
            }

            $allKeys = array_merge($allKeys, array_keys($dataRow));
        }

        // Reduce this array to only the unique keys.
        $allKeys = array_unique($allKeys);

        $this->setColumns($allKeys);
    }

    /**
     * Sort the members of the columns array by index and reset the indexes
     * to start from zero and count up.
     */
    protected function sortColumns()
    {
        usort(
            $this->columns,
            function ($a, $b) {
                if ($a->index == $b->index) {
                    return 0;
                }
                return ($a->index < $b->index) ? -1 : 1;
            }
        );

        array_walk(
            $this->columns,
            function ($column, $index) use (&$columnsIndex) {
                $column->index = $index;
            }
        );

    }

    /**
     * Replace the current columns member with an array of objects.
     */
    protected function restructureColumns()
    {
        $index = 0;
        foreach ($this->columns as &$column) {
            $this->retructureColumn($column, $index);
            $index++;
        }
    }

    /**
     * Given an item from the columns array, convert it to an StdClass object
     * with the minimum required properties.
     *
     * @param mixed $column
     * @param int $index
     * @throws Exception
     */
    protected function retructureColumn(&$column, $index)
    {
        // Convert the column variable to a StdClass object, if needed.

        if (is_array($column) && self::isAssoc($column)) {

            // Cast associatve array to StdClass object.
            $column = (object) $column;

        } elseif (is_string($column)) {

            // If the item is a string build an object using this as both the
            // key and the heading.
            $column = (object) array(
                'index' => $index,
                'key' => $column,
                'heading' => $column
            );

        }

        // Ensure the variable is a proper StdClass object with the required
        // properties: index, key, and heading.

        if (is_object($column)) {

            if (!isset($column->index)) {
                // Use the passed value.
                $column->index = $index;
            }

            if (!isset($column->heading)) {
                // Use the key as the heading.
                if (isset($column->key)) {
                    $column->heading = $column->key;
                } else {
                    throw new Exception('Missing column heading and key.');
                }
            }

            if (!isset($column->key)) {
                // Use the heading as the key.
                $column->key = $column->heading;
            }

            if (isset($column->format)) {
                if (!is_object($column->format)) {
                    $column->format = (object) array(
                        'type' => $column->format
                    );
                }
            }

        } else {
            throw new Exception('Unexpected value in columns array');
        }

    }

    // ------------------------------------------------------------------------

    /**
     * Replace the current columns member with an array of objects.
     */
    protected function restructureData()
    {
        // Numeric array
        if (is_array($this->data) && !self::isAssoc($this->data)) {
            foreach ($this->data as &$row) {
                $this->restructureDataRow($row);
            }
        } else {
            throw new Exception('Data must be a numeric array of objects or associative arrays');
        }
    }

    /**
     * Given an item from the columns array, convert it to an StdClass object
     * with the minimum required properties.
     *
     * @param mixed $row
     * @throws Exception
     */
    protected function restructureDataRow(&$row)
    {
        // Cast the row to a StdClass instance if it is an array.
        if (is_array($row)) {
            $row = (object) $row;
        }

        if (!is_object($row)) {
            throw new Exception('Data must be a numeric array of objects or associative arrays');
        }

        // Iterate over the members and ensure each is a StdClass object
        // with a data member.
        foreach ($row as &$value) {
            $this->restructureDataCell($value);
        }
    }

    /**
     * Convert the value for a cell in a data row to a StdClass object.
     *
     * @param mixed $cell
     * @throws Exception
     */
    protected function restructureDataCell(&$cell)
    {
        if (is_array($cell) && self::isAssoc($cell)) {
            // Cast associatve array to StdClass object.
            $cell = (object) $cell;
        } elseif (!is_object($cell)) {
            $cell = (object) array(
                'value' => $cell
            );
        }

        // Ensure the variable is a proper StdClass object with the required
        // property value.

        if (is_object($cell)) {
            if (!isset($cell->value)) {
                throw new Exception('Missing data value');
            }
        } else {
            throw new Exception('Unexpected value in data array');
        }

    }

    // ------------------------------------------------------------------------

    /**
     * Parse the options and construct a sort function
     *
     * @throws Exception
     */
    protected function buildSortFunction()
    {
        if (isset($this->options) && isset($this->options->sort)) {
            $sortOptions = $this->options->sort;
        } else {
            return;
        }

        $sortFns = array();

        foreach ($sortOptions as $sortOption) {

            $reverse = false;

            if (is_string($sortOption)) {

                // Use the string as the column key.
                $columnKey = $sortOption;

                if ($columnKey[0] === '!') {
                    $reverse = true;
                    $columnKey = substr($columnKey, 1);
                }

            } else if (is_object($sortOption)) {

                // Object
                if (!isset($sortOption->column)) {
                    throw new Exception('Sort object must contain a column member');
                }
                $columnKey = $sortOption->column;

                if (isset($sortOption->reverse)) {
                    $reverse = $sortOption->reverse;
                }

            } else {
                // Fail if not a string or object.
                throw new Exception('Unexpected item in options.sort');
            }

            // Append a new sort function.
            $sortFns[] = self::makeSortFunction($columnKey, $reverse);

        }

        // Chain the array of callbacks together into one.
        $this->sortFunction = self::makeComplexSortFunction($sortFns);

    }

    /**
     * Sort the data row using the sortFunction member.
     *
     * This is called automatically on setData(), so you really only need to
     * call it explicitly if you set the sortFunction member after setting
     * the data.
     */
    protected function sortData()
    {
        if (is_callable($this->sortFunction)) {
            usort($this->data, $this->sortFunction);
        }
    }

    /**
     * Create and return a comparison function the compares the sortValue or
     * value members of the given column for two rows.
     *
     * If a comparison function is passed as $nextFunction, the new function
     * use that in the event of an equal comparion.
     *
     * @param string $columnKey
     * @param bool $reverse
     * @return callable
     */
    public static function makeSortFunction($columnKey, $reverse = false)
    {
        $order = $reverse ? -1 : 1;

        $fn = function ($a, $b) use ($columnKey, $order) {

            // Prefer the optional sortValue or value for sorting.
            if (isset($a->{$columnKey}->sortValue)) {
                $aVal = $a->{$columnKey}->sortValue;
            } else {
                $aVal = $a->{$columnKey}->value;
            }

            if (isset($b->{$columnKey}->sortValue)) {
                $bVal = $b->{$columnKey}->sortValue;
            } else {
                $bVal = $b->{$columnKey}->value;
            }

            if ($aVal == $bVal) {
                return 0;
            }

            return $order * ($aVal > $bVal ? 1 : -1);

        };

        return $fn;

    }

    /**
     * Given an ordered array of comparison function, return one function that
     * starts with the first and uses the subsequent functions in order in the
     * event of equal items.
     *
     * @param $sortFnArr
     * @param int $index
     * @return callable
     * @throws \Exception
     */
    public static function makeComplexSortFunction($sortFnArr, $index = 0)
    {
        if (isset($sortFnArr[$index])) {
            $fn1 = $sortFnArr[$index];
        } else {
            throw new Exception('First argument must be an array conatining at least one callable');
        }

        $fn2 = null;
        if (isset($sortFnArr[$index + 1])) {
            $fn2 = self::makeComplexSortFunction($sortFnArr, $index + 1);
        }

        return self::makeChainedSortFunction($fn1, $fn2);
    }

    /**
     * Given two comparison functions, return a comparison function that uses
     * the first unless it evaluates as equal, then uses the second.
     *
     * @param $fn1
     * @param $fn2
     * @return callable
     */
    public static function makeChainedSortFunction($fn1, $fn2)
    {
        if (!is_callable($fn2)) {
            return $fn1;
        }

        return function ($a, $b) use ($fn1, $fn2) {

            $comp = $fn1($a, $b);

            if ($comp !== 0) {
                return $comp;
            }

            return $fn2($a, $b);

        };

    }

    // ------------------------------------------------------------------------

    /**
     * Return if an array is associative.
     *
     * @param array $arr
     * @return bool
     */
    static private function isAssoc($arr)
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

}
