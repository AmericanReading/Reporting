<?php

use AmericanReading\Reporting\ExcelReport;
use AmericanReading\Reporting\HtmlReport;

require_once __DIR__ . '/../../vendor/autoload.php';

$columns = ['Prefix', 'First', 'Middle', 'Last'];
$data = [
    [
        'First' => 'Philip',
        'Middle' => 'J.',
        'Last' => 'Fry'
    ],
    [
        'First' => 'Turanga',
        'Last' => 'Leela'
    ],
    [
        'Prefix' => 'Dr.',
        'First' => 'Amy',
        'Last' => 'Wong'
    ],
    [
        'Prefix' => 'Dr.',
        'First' => 'John',
        'Last' => 'Zoidberg'
    ],
    [
        'First' => 'Hermes',
        'Last' => 'Conrad'
    ],
    [
        'Prefix' => 'Professor',
        'First' => 'Hubert',
        'Middle' => 'J.',
        'Last' => 'Farnsworth'
    ]
];

switch ($_SERVER['REQUEST_URI']) {
    case '/report.xlsx': {
        $report = new ExcelReport();
        $report->setColumns($columns);
        $report->setData($data);
        $report->filename = "Sample Excel Report.xlsx";
        $report->report();
        exit;
        break;
    }
    case '/report.html': {
        $report = new HtmlReport();
        $report->setColumns($columns);
        $report->setData($data);
        print $report->html();
        exit;
        break;
    }
}

?>
<p><a href="/report.xlsx" target="_blank">Excel Report</a></p>
<p><a href="/report.html" target="_blank">HTML Report</a></p>
