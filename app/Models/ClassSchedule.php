<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id', 'day_of_week', 'start_time', 'end_time',
        'room', 'meeting_link'
    ];

    // Relationships
    public function classModel()
    {
        return $this->belongsTo(ClassModel::class, 'class_id');
    }
}

