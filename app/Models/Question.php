<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'tutor_id', 'assignment_id', 'class_id',
        'subject', 'question', 'priority', 'category', 'status',
        'answer', 'answered_at'
    ];

    protected function casts(): array
    {
        return [
            'answered_at' => 'datetime',
        ];
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function classModel()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function attachments()
    {
        return $this->hasMany(QuestionAttachment::class);
    }
}
