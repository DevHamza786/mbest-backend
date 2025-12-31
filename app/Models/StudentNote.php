<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id', 'student_id', 'behavior_issues',
        'homework_completed', 'homework_notes', 'private_notes'
    ];

    protected function casts(): array
    {
        return [
            'homework_completed' => 'boolean',
        ];
    }

    // Relationships
    public function session()
    {
        return $this->belongsTo(TutoringSession::class, 'session_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}

