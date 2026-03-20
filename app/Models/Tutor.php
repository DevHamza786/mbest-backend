<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tutor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'department', 'specialization', 'hourly_rate',
        'subject_year_mapping', 'bio', 'qualifications', 'experience_years', 'is_available',
        'wwcc_number', 'wwcc_expiry_date', 'max_students_per_group'
    ];

    protected function casts(): array
    {
        return [
            'specialization' => 'array',
            'subject_year_mapping' => 'array',
            'hourly_rate' => 'decimal:2',
            'is_available' => 'boolean',
            'wwcc_expiry_date' => 'date',
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function classes()
    {
        return $this->hasMany(ClassModel::class);
    }

    public function sessions()
    {
        return $this->hasMany(TutoringSession::class, 'teacher_id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function availability()
    {
        return $this->hasMany(TutorAvailability::class);
    }
}

