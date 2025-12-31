<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'name', 'code', 'tutor_id', 'description', 'category',
        'level', 'capacity', 'enrolled', 'credits', 'duration',
        'status', 'start_date', 'end_date'
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    // Relationships
    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'class_student', 'class_id', 'student_id');
    }

    public function schedules()
    {
        return $this->hasMany(ClassSchedule::class, 'class_id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class, 'class_id');
    }

    public function resources()
    {
        return $this->hasMany(Resource::class, 'class_id');
    }

    public function sessions()
    {
        return $this->hasMany(TutoringSession::class, 'class_id');
    }
}

