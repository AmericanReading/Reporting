<?php

namespace AmericanReading\Reporting;

class HtmlReport extends ReportBase
{
    public $tableClass;

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
