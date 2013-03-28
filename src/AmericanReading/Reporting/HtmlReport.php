<?php

namespace AmericanReading\Reporting;

class HtmlReport extends ReportBase
{
    public function html()
    {
        $html = '';

        if (!isset($this->data)) {
            return '';
        }

        $this->dump();

        $html .= '<table>';

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

            $cellData = $rowData->{$column->key};

            $html .= $this->htmlCell($column, $cellData);
        }

        $html .= '</tr>';

        return $html;
    }

    protected function htmlCell($column, $data)
    {
        $cssClass = 'column-' . $column->index;
        if (isset($column->class)) {
            $cssClass .= ' ' . $column->class;
        }

        $sortValue = isset($data->sortValue) ? $data->sortValue : $data->value;

        $html = '';
        $html .= '<td';
        $html .= ' class="' . $cssClass . '"';
        $html .= ' data-column-key="' . $column->key . '"';
        $html .= ' data-sort-value="' . $sortValue . '"';
        $html .= '>';
        $html .= $data->value;
        $html .= '</td>';

        return $html;
    }

}