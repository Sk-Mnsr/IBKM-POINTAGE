<?php

namespace App\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SpreadsheetExporter
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<list<string|int|float|null>>  $rows
     * @param  list<string|int|float|null>|null  $totals
     */
    public function download(
        string $fileName,
        string $title,
        string $subtitle,
        array $headings,
        iterable $rows,
        ?array $totals = null,
    ): BinaryFileResponse {
        $path = tempnam(sys_get_temp_dir(), 'pointage');

        $options = new Options;
        $options->setColumnWidth(24, 1);
        $options->setColumnWidthForRange(16, 2, max(2, count($headings)));

        $writer = new Writer($options);
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Pointage');

        $writer->addRow(Row::fromValuesWithStyle([config('pointage.institute_name')], new Style(fontBold: true, fontSize: 14)));
        $writer->addRow(Row::fromValuesWithStyle([$title], new Style(fontBold: true, fontSize: 12)));
        $writer->addRow(Row::fromValues([$subtitle]));
        $writer->addRow(Row::fromValues(['']));
        $writer->addRow(Row::fromValuesWithStyle($headings, new Style(
            fontBold: true,
            fontColor: Color::WHITE,
            backgroundColor: '111311',
        )));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        if ($totals !== null) {
            $writer->addRow(Row::fromValuesWithStyle($totals, new Style(fontBold: true, backgroundColor: 'EEF3E2')));
        }

        $writer->close();

        return response()
            ->download($path, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->deleteFileAfterSend();
    }
}
