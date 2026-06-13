<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Lead;
use App\Models\OfferLetter;
use App\Models\SelectionSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdmissionQuickSearchService
{
    public function search(string $query, ?User $user = null): Collection
    {
        $term = '%' . $query . '%';
        $results = collect();

        Lead::where('name', 'like', $term)->orWhere('phone', 'like', $term)->orWhere('email', 'like', $term)
            ->limit(8)->get()->each(fn ($lead) => $results->push($this->result('lead', $lead->id, $lead->name, route('admission.leads.show', $lead))));

        Applicant::with('user')->where('application_number', 'like', $term)
            ->orWhere('personal_data', 'like', $term)
            ->limit(8)->get()->each(fn ($applicant) => $results->push($this->result('applicant', $applicant->id, $applicant->application_number . ' - ' . ($applicant->user?->name ?? 'Applicant'), route('admission.applicants.show', $applicant))));

        OfferLetter::where('offer_number', 'like', $term)->limit(5)->get()
            ->each(fn ($offer) => $results->push($this->result('offer', $offer->id, $offer->offer_number, route('admission.offer-letters.show', $offer))));

        SelectionSession::where('session_name', 'like', $term)->limit(5)->get()
            ->each(fn ($session) => $results->push($this->result('session', $session->id, $session->session_name, route('admission.sessions.show', $session))));

        DB::table('admission_quick_search_logs')->insert([
            'query' => $query,
            'result_type' => $results->first()['type'] ?? null,
            'result_id' => $results->first()['id'] ?? null,
            'user_id' => $user?->id,
            'metadata' => json_encode(['count' => $results->count()]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $results->values();
    }

    private function result(string $type, int $id, string $label, string $url): array
    {
        return compact('type', 'id', 'label', 'url');
    }
}
