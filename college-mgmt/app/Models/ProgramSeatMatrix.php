<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramSeatMatrix extends Model
{
    protected $table = 'program_seat_matrix';

    protected $fillable = [
        'program_id', 'batch_id',
        'general_seats', 'obc_seats', 'obc_nc_seats', 'sc_seats', 'st_seats',
        'ews_seats', 'pwd_seats', 'nri_seats', 'management_quota_seats',
        'total_seats', 'state_quota_percentage', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }

    public function getTotalSeatsAttribute(): int
    {
        return (int) (
            $this->general_seats +
            $this->obc_seats +
            $this->obc_nc_seats +
            $this->sc_seats +
            $this->st_seats +
            $this->ews_seats +
            $this->nri_seats +
            $this->management_quota_seats
            // PWD seats are supernumerary — not added to total intake
        );
    }

    public function getSeatsForCategory(string $category): int
    {
        $map = [
            'general'          => 'general_seats',
            'obc'              => 'obc_seats',
            'obc_nc'           => 'obc_nc_seats',
            'sc'               => 'sc_seats',
            'st'               => 'st_seats',
            'ews'              => 'ews_seats',
            'pwd'              => 'pwd_seats',
            'nri'              => 'nri_seats',
            'management_quota' => 'management_quota_seats',
        ];
        $col = $map[$category] ?? null;
        return $col ? (int) $this->$col : 0;
    }

    public function getRemainingSeats(string $category, int $batchId): int
    {
        $total = $this->getSeatsForCategory($category);
        $selected = MeritListEntry::where('program_id', $this->program_id)
            ->where('batch_id', $batchId)
            ->where('category', $category)
            ->where('decision', 'selected')
            ->count();
        return max(0, $total - $selected);
    }

    public static function defaults(): array
    {
        // Typical 120-seat PGDM with management/NRI quota
        // OBC 27%, SC 15%, ST 7.5%, EWS 10% of govt. seats (102 govt + 18 mgmt + 6 NRI = 126 total minus PWD supernumerary)
        return [
            'general_seats'          => 60,
            'obc_seats'              => 16,
            'obc_nc_seats'           => 11,
            'sc_seats'               => 15,
            'st_seats'               => 8,
            'ews_seats'              => 12,
            'pwd_seats'              => 3,   // supernumerary (horizontal reservation)
            'nri_seats'              => 6,
            'management_quota_seats' => 12,
            'total_seats'            => 140, // stored for display
            'state_quota_percentage' => 0,
        ];
    }
}
