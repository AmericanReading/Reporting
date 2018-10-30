<?php

namespace AmericanReading\Reporting;

use Couchbase\Exception;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelReport extends ReportBase
{
    /**
     * Prefix used for the temp file
     *
     * @var string
     */
    public $filenamePrefix = 'xlsreport';

    /**
     * Filename provided in the headers to indicate a name on download.
     *
     * @var string
     */
    public $filename = 'report.xlsx';

    /**
     * Generate the report as an Excel document.
     *
     * This menthod can output the headers and document. Or, if you pass true
     * for $returnAsString, the method will outpt anything, but will return
     * the document as a string.
     *
     * @param bool $returnAsString
     * @return string
     * @throws \Exception
     */
    public function report($returnAsString = false)
    {
        $tmp = $this->writeToTempFile();

        if ($returnAsString) {
            $contents = file_get_contents($tmp);
            unlink($tmp);
            return $contents;
        }

        // Output the content-type header and the contents of the file.
        header("Content-Length: " . filesize($tmp));
        header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $this->filename . '"');
        readfile($tmp);

        // Remove the temp file.
        unlink($tmp);
        exit;
    }

    /**
     * Write the report to a temp file and return the path.
     *
     * @return string Path to the newly created temp file.
     * @throws \Exception
     */
    public function writeToTempFile()
    {
        // Get a temporary file to write to.
        $tmp = tempnam(sys_get_temp_dir(), $this->filenamePrefix);
        if ($tmp === false) {
            throw new Exception('Cannot generate report. Unable to write to temp file: ' . $tmp);
        }

        $spreadsheet = $this->phpExcelReport();

        // Create the writer and save the file.
        $writer = new Xlsx($spreadsheet);
        $writer->save($tmp);

        return $tmp;
    }

    /**
     * Build the report as a PHPExcel instance.
     *
     * @return Spreadsheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function phpExcelReport()
    {
        if (!isset($this->data)) {
            return null;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Add the data to the sheet.
        $this->addHeaderRow($sheet);
        $this->addDataRows($sheet);

        $this->formatColumns($sheet);

        return $spreadsheet;
    }

    /**
     * Write the columns names, style them, and setup the split on the passed
     * PHPExcel worksheet instance.
     *
     * @param Worksheet $sheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function addHeaderRow($sheet)
    {
        foreach ($this->columns as $column) {
            $cell = $sheet->getCellByColumnAndRow($column->index + 1, 1);
            $cell->setValue($column->heading);
            $col = $cell->getColumn();
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        if (isset($cell)) {
            $cellRange = 'A1:' . $cell->getCoordinate();
            $styleArray = array(
                'font' => array(
                    'bold' => true,
                )
            );
            $sheet->getStyle($cellRange)->applyFromArray($styleArray);
        }

        $sheet->freezePaneByColumnAndRow(0,2);
        $sheet->calculateColumnWidths();
    }

    /**
     * Write the columns names, style them, and setup the split on the passed
     * PHPExcel worksheet instance.
     *
     * @param Worksheet $sheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function addDataRows($sheet)
    {
        $rowIndex = $sheet->getHighestRow() + 1;

        foreach ($this->data as $row) {
            $this->addDataRow($sheet, $row, $rowIndex);
            $rowIndex += 1;
        }
    }

    /**
     * Write one row of data from the passed array the worksheet.
     *
     * @param Worksheet $sheet The worksheet to modify
     * @param array $row Associative array representing one row of data
     * @param int $rowIndex The 1-based index of the row to write to.
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function addDataRow($sheet, $row, $rowIndex)
    {
        foreach ($this->columns as $column) {
            if (isset($row->{$column->key})) {
                $cell = $sheet->getCellByColumnAndRow(
                    $column->index + 1, $rowIndex);
                $data = $row->{$column->key};
                $this->setDataCellValue($cell, $data, $column);
            }
        }
    }

    /**
     * @param Cell $cell
     * @param object $data
     * @param object $column
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function setDataCellValue($cell, $data, $column) {

        if ($data->value === '') {
            return;
        }

        // Dates need to be converted to Excel format dates first.
        if (isset($column->format) && $column->format->type === 'date') {
            $dataValue = Date::PHPToExcel(strtotime($data->value));
            $cell->setValue($dataValue);
            return;
        }

        $cell->setValue($data->value);
    }

    /**
     * @param Worksheet $sheet
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    protected function formatColumns($sheet) {

        $highest = $sheet->getHighestRow();

        foreach ($this->columns as $column) {
            if (isset($column->format)) {

                $xlsColumn = $sheet->getCellByColumnAndRow($column->index, 1)->getColumn();
                $range = $xlsColumn . '2:' . $xlsColumn . $highest;

                switch ($column->format->type) {

                    case 'currency':
                        $sheet->getStyle($range)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_CURRENCY_USD_SIMPLE);
                        break;

                    case 'date':

                        $dateFormat = 'm/d/yyyy';
                        if (isset($column->format->options)) {
                            $dateFormat = self::phpDateFormatToExcelDate($column->format->options);
                        }

                        $sheet->getStyle($range)->getNumberFormat()->setFormatCode($dateFormat);
                        break;

                    case 'percentage':
                        $sheet->getStyle($range)->getNumberFormat()->setFormatCode(PHPExcel_Style_NumberFormat::FORMAT_PERCENTAGE);
                        break;

                    case 'singleDecimal':
                        $sheet->getStyle($range)->getNumberFormat()->setFormatCode("0.0");
                        break;
                }
            }
        }
    }

    /**
     * Given a string for use with PHP's date() function, return an
     * Excel-friendly format string.
     *
     * @param string $phpDateFormat
     * @return string
     */
    public static function phpDateFormatToExcelDate($phpDateFormat)
    {
        $lut = array(
            'j' => 'd',
            'd' => 'dd',
            'n' => 'm',
            'm' => 'mm',
            'y' => 'y',
            'Y' => 'yyyy'
        );

        return str_replace(
            array_keys($lut),
            array_values($lut),
            $phpDateFormat);
    }
}
