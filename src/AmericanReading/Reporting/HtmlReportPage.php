<?php

namespace AmericanReading\Reporting;

use AmericanReading\Reporting\HtmlReport;

class HtmlReportPage
{
    const DEFAULT_TEMPLATE = <<<'HTML'
<!DOCTYPE html>
<html>
    <head>
        <title>{TITLE}</title>
        <meta charset="utf-8" />
    </head>
    <body>
        <h1>{TITLE}</h1>
        {REPORT}
    </body>
</html>
HTML;

    public $report;
    public $mergeFields;
    public $template;

    /**
     * Create an instance for building markup of an entire page containing a
     * report.
     *
     * Customize the output by providing a custom template.
     *
     * @param null $data
     * @param string $template
     */
    public function __construct($data = null, $template = self::DEFAULT_TEMPLATE)
    {
        $this->report = new HtmlReport($data);
        $this->report->tableClass = 'report';
        $this->template = $template;
        $this->mergeFields = array();
    }

    /**
     * Generate and return markup for an entire HTML page containing a report.
     *
     * @return string
     */
    public function html()
    {
        if (!isset($this->report->title)) {
            $this->report->title = 'Report';
        }

        $mergeFields = array(
            '{REPORT}' => $this->report->html(),
            '{TITLE}' => $this->report->title
        );
        $mergeFields = array_merge($mergeFields, $this->mergeFields);

        return $this->stringFromTemplate($this->template, $mergeFields);
    }

    /**
     * Merge an associative array into a string template.
     *
     * @param string $template
     * @param array $mergeFields
     * @return string
     */
    protected function stringFromTemplate($template, $mergeFields)
    {
        return str_replace(
            array_keys($mergeFields),
            array_values($mergeFields),
            $template);
    }

}
