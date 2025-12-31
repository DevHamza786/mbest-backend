<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id', 'assignment_id', 'class_id', 'subject',
        'assessment', 'grade', 'max_grade', 'category', 'date', 'notes'
    ];

    protected function casts(): array
    {
        return [
            'grade' => 'decimal:2',
            'max_grade' => 'decimal:2',
            'date' => 'date',
        ];
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function classModel()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }
}

