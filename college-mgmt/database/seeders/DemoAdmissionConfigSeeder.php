<?php

namespace Database\Seeders;

use App\Models\AdmissionFeeInstallment;
use App\Models\AdmissionFormConfig;
use App\Models\Program;
use App\Models\ProgramSeatMatrix;
use App\Models\RequiredDocument;
use App\Models\ScoringParameter;
use App\Models\SelectionProcessStep;
use Illuminate\Database\Seeder;

class DemoAdmissionConfigSeeder extends Seeder
{
    public function run(): void
    {
        $program = Program::where('code', 'PGDM')->first();
        if (! $program) {
            return;
        }

        foreach (RequiredDocument::defaults() as $doc) {
            RequiredDocument::firstOrCreate(
                ['program_id' => $program->id, 'name' => $doc['name']],
                array_merge($doc, ['program_id' => $program->id])
            );
        }

        SelectionProcessStep::firstOrCreate(['program_id' => $program->id, 'step_order' => 1], [
            'name' => 'Written Ability Test',
            'type' => 'wat',
            'max_score' => 30,
            'weightage' => 20,
            'instructions' => 'Candidates write a 300-word essay on a given topic in 20 minutes.',
        ]);
        $gd = SelectionProcessStep::firstOrCreate(['program_id' => $program->id, 'step_order' => 2], [
            'name' => 'Group Discussion',
            'type' => 'gd',
            'max_score' => 40,
            'weightage' => 30,
            'instructions' => 'Groups of 8-10 candidates. 15-minute topic discussion.',
        ]);
        $pi = SelectionProcessStep::firstOrCreate(['program_id' => $program->id, 'step_order' => 3], [
            'name' => 'Personal Interview',
            'type' => 'pi',
            'max_score' => 100,
            'weightage' => 50,
            'instructions' => 'Individual 20-minute interview with 2 evaluators.',
        ]);

        foreach ([
            ['name' => 'Communication Skills', 'max_score' => 10],
            ['name' => 'Leadership Qualities', 'max_score' => 10],
            ['name' => 'Subject Knowledge', 'max_score' => 10],
            ['name' => 'Body Language & Confidence', 'max_score' => 10],
        ] as $i => $parameter) {
            ScoringParameter::firstOrCreate(
                ['selection_process_step_id' => $gd->id, 'name' => $parameter['name']],
                array_merge($parameter, ['selection_process_step_id' => $gd->id, 'sort_order' => $i + 1])
            );
        }

        foreach ([
            ['name' => 'Subject Knowledge', 'max_score' => 20],
            ['name' => 'Problem Solving', 'max_score' => 20],
            ['name' => 'Communication', 'max_score' => 20],
            ['name' => 'Career Clarity', 'max_score' => 20],
            ['name' => 'Overall Impression', 'max_score' => 20],
        ] as $i => $parameter) {
            ScoringParameter::firstOrCreate(
                ['selection_process_step_id' => $pi->id, 'name' => $parameter['name']],
                array_merge($parameter, ['selection_process_step_id' => $pi->id, 'sort_order' => $i + 1])
            );
        }

        AdmissionFeeInstallment::firstOrCreate(['program_id' => $program->id, 'installment_number' => 1], [
            'name' => 'Registration Fee',
            'amount' => 10000,
            'due_date' => '2024-03-31',
            'description' => 'Non-refundable registration fee to confirm your seat',
        ]);
        AdmissionFeeInstallment::firstOrCreate(['program_id' => $program->id, 'installment_number' => 2], [
            'name' => 'First Semester Fee',
            'amount' => 140000,
            'due_date' => '2024-06-15',
            'description' => 'First semester tuition and other fees',
        ]);

        AdmissionFormConfig::firstOrCreate(['program_id' => $program->id], [
            'form_sections' => AdmissionFormConfig::getDefaultSections(),
            'is_active' => true,
        ]);

        ProgramSeatMatrix::firstOrCreate(['program_id' => $program->id, 'batch_id' => null], [
            'general_seats' => 60,
            'obc_seats' => 16,
            'obc_nc_seats' => 11,
            'sc_seats' => 15,
            'st_seats' => 7,
            'ews_seats' => 12,
            'pwd_seats' => 3,
            'nri_seats' => 6,
            'management_quota_seats' => 12,
            'total_seats' => 139,
            'state_quota_percentage' => 0,
            'is_active' => true,
        ]);
    }
}
