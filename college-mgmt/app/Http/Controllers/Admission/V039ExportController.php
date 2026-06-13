<?php

namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Services\AdmissionFinalExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class V039ExportController extends Controller
{
    public function __invoke(string $type, Request $request, AdmissionFinalExportService $service): StreamedResponse
    {
        $rows = $service->rows($type, $request->query());
        $service->log($type, $request->headers->get('referer', 'admission'), $request->query(), count($rows), $request->user());

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            $headers = collect($rows)->first() ? array_keys((array) collect($rows)->first()) : ['message'];
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, collect($headers)->map(fn ($key) => is_scalar($row[$key] ?? null) ? $row[$key] : json_encode($row[$key] ?? null))->all());
            }
            fclose($out);
        }, 'admission-'.$type.'-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv']);
    }
}
