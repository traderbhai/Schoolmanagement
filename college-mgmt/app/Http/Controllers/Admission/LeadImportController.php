<?php
namespace App\Http\Controllers\Admission;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LeadImportController extends Controller
{
    public function showImportForm()
    {
        return view('admission.leads.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        // Read and validate header
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'The CSV file is empty.']);
        }
        $header = array_map('strtolower', array_map('trim', $header));

        if (!in_array('name', $header) || !in_array('email', $header)) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'CSV must have at minimum: name, email columns.']);
        }

        // Build program code→id map
        $programMap = Program::pluck('id', 'code')->toArray();
        $validSources = ['web_form','referral','advertisement','social_media','event','agent','other'];

        $results = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) !== count($header)) {
                $results['errors'][] = "Row {$rowNum}: column count mismatch, skipped.";
                $results['skipped']++;
                continue;
            }
            $data = array_combine($header, array_map('trim', $row));

            // Validate required fields
            if (empty($data['name']) || empty($data['email'])) {
                $results['errors'][] = "Row {$rowNum}: name and email are required, skipped.";
                $results['skipped']++;
                continue;
            }
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $results['errors'][] = "Row {$rowNum}: invalid email '{$data['email']}', skipped.";
                $results['skipped']++;
                continue;
            }

            $programId = null;
            if (!empty($data['program_code'] ?? '')) {
                $programId = $programMap[strtoupper($data['program_code'])] ?? null;
                if (!$programId) {
                    $results['errors'][] = "Row {$rowNum}: unknown program code '{$data['program_code']}', importing without program.";
                }
            }

            $source = 'web_form';
            if (!empty($data['source'] ?? '') && in_array($data['source'], $validSources)) {
                $source = $data['source'];
            }

            $leadData = [
                'name'       => $data['name'],
                'phone'      => $data['phone'] ?? null,
                'program_id' => $programId,
                'source'     => $source,
                'notes'      => $data['notes'] ?? null,
            ];

            $existing = Lead::where('email', $data['email'])->first();
            if ($existing) {
                if ($existing->isConverted()) {
                    $results['skipped']++;
                    continue;
                }
                $existing->update($leadData);
                $results['updated']++;
            } else {
                Lead::create(array_merge($leadData, ['email' => $data['email']]));
                $results['created']++;
            }
        }
        fclose($handle);

        return back()->with('import_results', $results);
    }
}
