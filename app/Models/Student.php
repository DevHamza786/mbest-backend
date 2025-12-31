<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'parent_id', 'enrollment_id', 'grade',
        'school', 'emergency_contact_name', 'emergency_contact_phone'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function parents()
    {
        return $this->belongsToMany(User::class, 'parent_student', 'student_id', 'parent_id');
    }

    public function classes()
    {
        return $this->belongsToMany(ClassModel::class, 'class_student', 'student_id', 'class_id');
    }

    public function sessions()
    {
        return $this->belongsToMany(TutoringSession::class, 'session_student', 'student_id', 'session_id');
    }

    public function assignments()
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function grades()
    {
        return $this->hasMany(Grade::class);
    }
}

