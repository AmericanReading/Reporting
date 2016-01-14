<?php

namespace AmericanReading\Reporting;

use Exception;
use PHPExcel;
use PHPExcel_Cell;
use PHPExcel_IOFactory;
use PHPExcel_Shared_Date;
use PHPExcel_Style_NumberFormat;
use PHPExcel_Worksheet;

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

        // Output the content-type header and the contents of the file.
        header("Content-Length: " . filesize($tmp));
        header('Content-type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename=' . $this->filename);
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

        $xls = $this->phpExcelReport();

        // Create the writer and save the file.
        $objWriter = PHPExcel_IOFactory::createWriter($xls, 'Excel2007');
        $objWriter->save($tmp);

        return $tmp;
    }

    /**
     * Build the report as a PHPExcel instance.
     *
     * @return PHPExcel
     */
    public function phpExcelReport()
    {
        if (!isset($this->data)) {
            return null;
        }

        // Create a new instance and active sheet.
        $phpExcel = new PHPExcel();
        $phpExcel->setActiveSheetIndex(0);

        // Reference the first sheet.
        $sheet = $phpExcel->getActiveSheet();

        // Add the data to the sheet.
        $this->addHeaderRow($sheet);
        $this->addDataRows($sheet);

        $this->formatColumns($sheet);

        return $phpExcel;

    }

    /**
     * Write the columns names, style them, and setup the split on the passed
     * PHPExcel worksheet instance.
     *
     * @param \PHPExcel_Worksheet $sheet
     */
    protected function addHeaderRow($sheet)
    {
        foreach ($this->columns as $column) {
            $cell = $sheet->getCellByColumnAndRow($column->index, 1);
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
     * @param \PHPExcel_Worksheet $sheet
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
     * @param PHPExcel_Worksheet $sheet  The worksheet to modify
     * @param array $row  Associative array representing one row of data
     * @param int $rowIndex  The 1-based index of the row to write to.
     */
    protected function addDataRow($sheet, $row, $rowIndex) {

        foreach ($this->columns as $column) {

            if (isset($row->{$column->key})) {
                $cell = $sheet->getCellByColumnAndRow($column->index, $rowIndex);
                $data = $row->{$column->key};
                $this->setDataCellValue($cell, $data, $column);
            }

        }

    }

    /**
     * @param PHPExcel_Cell $cell
     * @param object $data
     * @param object $column
     */
    protected function setDataCellValue($cell, $data, $column) {

        if ($data->value === '') {
            return;
        }

        // Dates need to be converted to Excel format dates first.
        if (isset($column->format) && $column->format->type === 'date') {
            $dataValue = PHPExcel_Shared_Date::PHPToExcel(strtotime($data->value));
            $cell->setValue($dataValue);
            return;
        }

        $cell->setValue($data->value);
    }

    /**
     * @param PHPExcel_Worksheet $sheet
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
