<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutorAvailability extends Model
{
    use HasFactory;

    protected $table = 'tutor_availability';

    protected $fillable = [
        'tutor_id', 'day_of_week', 'start_time', 'end_time', 'is_available'
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    // Relationships
    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }
}

