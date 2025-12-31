<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'instructions', 'class_id', 'tutor_id',
        'due_date', 'max_points', 'submission_type', 'allowed_file_types', 'status'
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'allowed_file_types' => 'array',
        ];
    }

    // Relationships
    public function classModel()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }

    public function submissions()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}

