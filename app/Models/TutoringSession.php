<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutoringSession extends Model
{
    use HasFactory;

    protected $table = 'tutoring_sessions';

    protected $fillable = [
        'date', 'start_time', 'end_time', 'teacher_id', 'class_id', 'subject',
        'year_level', 'location', 'session_type', 'status',
        'lesson_note', 'topics_taught', 'homework_resources',
        'attendance_marked', 'ready_for_invoicing', 'color'
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'attendance_marked' => 'boolean',
            'ready_for_invoicing' => 'boolean',
        ];
    }

    // Relationships
    public function teacher()
    {
        return $this->belongsTo(Tutor::class, 'teacher_id');
    }

    public function classModel()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'session_student', 'session_id', 'student_id');
    }

    public function studentNotes()
    {
        return $this->hasMany(StudentNote::class, 'session_id');
    }
}

