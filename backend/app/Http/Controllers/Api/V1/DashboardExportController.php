<?php

namespace App\Http\Controllers\Api\V1;

use App\Support\Completeness\DashboardRecordsCollector;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardExportController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $canManage = $request->user()?->can('dashboard.manage') ?? false;
        $targetUserId = $request->input('user_id');

        if ($targetUserId !== null && (int) $targetUserId !== $request->user()->id) {
            abort_unless($canManage, 403);
        }

        $userId = $targetUserId !== null ? (int) $targetUserId : $request->user()->id;

        $entityTypes = (array) $request->input('entity_type', array_keys(DashboardRecordsCollector::ENTITY_TYPES));
        $severities = (array) $request->input('severity', []);

        $rows = (new DashboardRecordsCollector)->collect($userId, $entityTypes, $severities);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(
            ['Entity type', 'ID', 'Title (AR)', 'Title (EN)', 'Completeness %', 'Severity', 'Blocking gaps', 'Minor gaps', 'Open conflicts'],
            null,
            'A1',
        );

        $rowNumber = 2;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['entity_type'],
                $row['id'],
                $row['title']['ar'],
                $row['title']['en'],
                $row['completeness_pct'],
                $row['severity'],
                implode(', ', $row['blocking_gaps']),
                implode(', ', $row['minor_gaps']),
                $row['open_conflict_count'],
            ], null, "A{$rowNumber}");
            $rowNumber++;
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'gaps-report.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
