<?php

namespace App\Services;

use App\Models\AcademicDeanReportPack;

class AcademicDeanReportPackService
{
    public function dashboard(): array
    {
        return [
            'packs' => AcademicDeanReportPack::latest()->paginate(20),
            'active' => AcademicDeanReportPack::where('status', 'active')->count(),
            'scheduled' => AcademicDeanReportPack::where('schedule', '!=', 'manual')->count(),
        ];
    }

    public function generate(AcademicDeanReportPack $pack): AcademicDeanReportPack
    {
        $pack->update(['last_generated_at' => now(), 'metadata' => array_merge($pack->metadata ?? [], ['last_generation_status' => 'generated'])]);
        return $pack->fresh();
    }
}
