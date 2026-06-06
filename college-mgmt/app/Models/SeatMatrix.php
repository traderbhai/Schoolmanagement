<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SeatMatrix extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id', 'batch_id',
        'total_seats', 'general_seats', 'obc_seats', 'sc_seats', 'st_seats',
        'ews_seats', 'management_quota', 'nri_quota', 'defence_quota',
    ];

    public function program() { return $this->belongsTo(Program::class); }
    public function batch()   { return $this->belongsTo(Batch::class); }

    public function getReservedSeatsAttribute(): int
    {
        return $this->obc_seats + $this->sc_seats + $this->st_seats + $this->ews_seats;
    }

    public function getQuotaSeatsAttribute(): int
    {
        return $this->management_quota + $this->nri_quota + $this->defence_quota;
    }
}
