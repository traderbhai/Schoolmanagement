<?php

namespace App\Services;

use App\Models\AdmissionExportLog;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdmissionExportService
{
    public function csv(string $surface, string $type, iterable $rows, array $filters = [], ?User $actor = null): StreamedResponse
    {
        $data = collect($rows)->values();
        AdmissionExportLog::create(['export_type' => $type, 'surface' => $surface, 'filters' => $filters, 'row_count' => $data->count(), 'created_by' => $actor?->id]);

        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            if ($data->isNotEmpty()) {
                fputcsv($out, array_keys($data->first()));
            }
            foreach ($data as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $surface . '-' . $type . '.csv', ['Content-Type' => 'text/csv']);
    }
}
