<?php

namespace App\Services;

use App\Models\AcademicDeanRiskSnapshot;

class AcademicDeanRiskSnapshotService
{
    public function __construct(private AcademicDeanRiskService $risk, private AcademicDeanRiskConfigService $config) {}

    public function capture(): int
    {
        $count = 0;
        $snapshotDate = now()->toDateString();

        foreach ($this->risk->programRisks() as $risk) {
            $previous = AcademicDeanRiskSnapshot::where('program_id', $risk['program']->id)->latest('snapshot_date')->first();
            $trend = ! $previous ? 'insufficient_data' : ($risk['score'] > $previous->score ? 'worsening' : ($risk['score'] < $previous->score ? 'improving' : 'stable'));
            $snapshot = AcademicDeanRiskSnapshot::where('program_id', $risk['program']->id)
                ->whereDate('snapshot_date', $snapshotDate)
                ->first();

            $payload = [
                'program_id' => $risk['program']->id,
                'score' => $risk['score'],
                'band' => $this->config->band($risk['score']),
                'trend' => $trend,
                'metrics' => $risk['metrics'],
                'reasons' => $risk['reasons']->values()->all(),
                'snapshot_date' => $snapshotDate,
            ];

            $snapshot ? $snapshot->update($payload) : AcademicDeanRiskSnapshot::create($payload);
            $count++;
        }

        return $count;
    }

    public function history()
    {
        return AcademicDeanRiskSnapshot::with('program')->latest('snapshot_date')->paginate(30);
    }
}
