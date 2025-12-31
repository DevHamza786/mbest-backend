<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tutor extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'department', 'specialization', 'hourly_rate',
        'bio', 'qualifications', 'experience_years', 'is_available'
    ];

    protected function casts(): array
    {
        return [
            'specialization' => 'array',
            'hourly_rate' => 'decimal:2',
            'is_available' => 'boolean',
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

