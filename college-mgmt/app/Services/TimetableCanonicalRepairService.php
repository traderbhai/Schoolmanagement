<?php

namespace App\Services;

use App\Models\AcademicPmcTimetableGenerationItem;
use App\Models\AcademicPmcTimetableGenerationRun;
use App\Models\TimetableVersion;
use App\Models\User;

class TimetableCanonicalRepairService
{
    public function repairPublishedRunItems(?User $actor = null, array $filters = []): array
    {
        $query = AcademicPmcTimetableGenerationRun::with(['items.courseGroup'])
            ->whereNotNull('timetable_version_id')
            ->whereHas('timetableVersion', fn ($version) => $version->where('status', 'published'));

        if (! empty($filters['title'])) {
            $query->where('title', $filters['title']);
        }

        if (! empty($filters['run_id'])) {
            $query->whereKey((int) $filters['run_id']);
        }

        $runs = $query->get();
        $inspected = 0;
        $repaired = 0;
        $published = 0;
        $draft = 0;

        foreach ($runs as $run) {
            $version = TimetableVersion::find($run->timetable_version_id);
            if (! $version || $version->status !== 'published') {
                continue;
            }

            foreach ($run->items as $item) {
                $inspected++;
                $group = $item->courseGroup;
                $isOfficialSession = in_array($item->status, ['scheduled', 'published', 'locked'], true);
                $publishedAt = $isOfficialSession ? ($item->published_at ?: $version->published_at ?: now()) : null;
                $publishedBy = $isOfficialSession ? ($item->published_by ?: $version->published_by ?: $actor?->id) : null;
                $metadata = $item->metadata ?: [];
                $metadata['canonical_identity_repaired'] = true;
                $metadata['canonical_identity_repaired_at'] ??= now()->toDateTimeString();
                $metadata['canonical_identity_repaired_by'] ??= $actor?->id;
                $metadata['official_source'] = 'academic_pmc_timetable_generation_items';
                $metadata['timetable_version_id'] = $version->id;

                $data = [
                    'timetable_version_id' => $version->id,
                    'program_id' => $item->program_id ?: ($group?->program_id ?: $run->program_id ?: $version->program_id),
                    'batch_id' => $item->batch_id ?: ($group?->batch_id ?: $run->batch_id ?: $version->batch_id),
                    'term_id' => $item->term_id ?: ($group?->term_id ?: $run->term_id ?: $version->term_id),
                    'subject_id' => $item->subject_id ?: $group?->subject_id,
                    'source_type' => $item->source_type ?: 'canonical_repair',
                    'official_status' => $isOfficialSession ? 'published' : 'draft',
                    'published_at' => $publishedAt,
                    'published_by' => $publishedBy,
                    'metadata' => $metadata,
                ];

                $dirty = false;
                foreach ($data as $key => $value) {
                    if ($key === 'metadata') {
                        $dirty = $dirty || (($item->metadata ?: []) != $value);
                        continue;
                    }

                    if ((string) ($item->{$key} ?? '') !== (string) ($value ?? '')) {
                        $dirty = true;
                        break;
                    }
                }

                if ($dirty) {
                    $item->forceFill($data)->save();
                    $repaired++;
                }
                $isOfficialSession ? $published++ : $draft++;
            }
        }

        return compact('inspected', 'repaired', 'published', 'draft');
    }
}
