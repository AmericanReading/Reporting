<?php

namespace AmericanReading\Reporting;

class HtmlReport extends ReportBase
{
    /**
     * Optional CSS class to include in the table markup.
     *
     * @var string
     */
    public $tableClass;

    /**
     * Associative array of callables to use for formatting data.
     *
     * To use format functions, include a format member in the columns object
     * for one or more columns. For simple functions that will only take the
     * cell value as a parameter, you may supply the type of the format alone
     * as a string. For example:
     *
     * {
     *     ...other column description members...
     *     "format": "currency"
     * }
     *
     * For functions that require a second parameter, specify the format as an
     * object with "type" and "options" members.
     *
     * {
     *     ...other column description members...
     *     "format": {
     *          "type": "date",
     *          "options": "j/n/Y"
     *      }
     * }
     *
     * Several formats are created automatically, including "currency", "date",
     * and "percentage". You may add or modify them as needed. The keys in the
     * formatFunctions array correspond with the "type" in the columns
     * description.
     *
     * @var array
     */
    public $formatFunctions;

    /**
     * Create a new Report that produce an HTML representation.
     *
     * @param array|null $reportConfiguration
     * @throws \Exception
     */
    public function __construct($reportConfiguration = null)
    {
        parent::__construct($reportConfiguration);

        $this->formatFunctions = array(

            'currency' => function ($value) {
                return "$" . number_format($value, 2);
            },
            'date' => function ($value, $format = 'Y-m-d') {
                return date($format, strtotime($value));
            },
            'percentage' => function ($value) {
                return ($value * 100) . "%";
            }

        );
    }

    /**
     * Return the HTML representation of the repot.
     *
     * @return string
     */
    public function html()
    {
        if (!isset($this->data)) {
            return '';
        }

        $html = '<table';
        if (isset($this->tableClass)) {
            $html .= ' class="' . $this->tableClass .'"';
        }
        $html .= '>';

        if (isset($this->title)) {
            $html .= '<caption>' . $this->title .'</caption>';
        }

        $html .= '<thead>';
        $html .= '<tr>';
        foreach ($this->columns as $column) {
            $html .= $this->htmlHeading($column);
        }
        $html .= '</tr>';
        $html .= '</thead>';

        $html .= '<tbody>';
        foreach ($this->data as $row) {
            $html .= $this->htmlRow($row);
        }
        $html .= '</tbody>';

        $html .= '</table>';

        return $html;
    }

    /**
     * Return markup for a TH elemen for a given column.
     *
     * @param object $column
     * @return string
     */
    protected function htmlHeading($column)
    {
        $html = '';

        $cssClass = 'column-' . $column->index;
        if (isset($column->class)) {
            $cssClass .= ' ' . $column->class;
        }

        $html .= '<th';
        $html .= ' class="' . $cssClass . '"';
        $html .= ' data-column-key="' . $column->key . '"';
        $html .= '>';
        $html .= $column->heading;
        $html .= '</th>';

        return $html;
    }

    /**
     * Given an array representing one row, return a string for the row.
     *
     * @param array $rowData
     * @return string
     */
    protected function htmlRow($rowData)
    {
        $html = '';

        $html .= '<tr>';

        foreach ($this->columns as $column) {

            if (isset($rowData->{$column->key})) {
                $cellData = $rowData->{$column->key};
                $html .= $this->htmlCell($column, $cellData);
            } else {
                $html .= $this->htmlCell($column);
            }

        }

        $html .= '</tr>';

        return $html;
    }

    /**
     * Return markup for a single TD element given a column and data.
     *
     * @param object $column
     * @param object|null $data
     * @return string
     */
    protected function htmlCell($column, $data = null)
    {
        if (is_null($data)) {
            $data = (object) array(
                'value' => ''
            );
        }

        $cssClass = 'column-' . $column->index;
        if (isset($column->class)) {
            $cssClass .= ' ' . $column->class;
        }

        $sortValue = isset($data->sortValue) ? $data->sortValue : $data->value;

        $displayValue = $this->htmlCellValue($column, $data);

        $html = '';
        $html .= '<td';
        $html .= ' class="' . $cssClass . '"';
        $html .= ' data-column-key="' . $column->key . '"';
        $html .= ' data-sort-value="' . $sortValue . '"';
        $html .= '>';
        $html .= $displayValue;
        $html .= '</td>';

        return $html;
    }

    /**
     * Process the value of the data against the format functions.
     *
     * @param object $column
     * @param object $data
     * @return mixed
     */
    protected function htmlCellValue($column, $data)
    {
        if (isset($column->format)) {

            $format = $column->format;

            if (isset($this->formatFunctions[$format->type])) {
                if (isset($format->options)) {
                    return $this->formatFunctions[$format->type]($data->value, $format->options);
                } else {
                    return $this->formatFunctions[$format->type]($data->value);
                }
            }

        }

        return $data->value;
    }
}
