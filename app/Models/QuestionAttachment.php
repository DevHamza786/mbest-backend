<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id', 'file_path', 'file_name', 'file_type', 'file_size'
    ];

    // Relationships
    public function question()
    {
        return $this->belongsTo(Question::class);
    }
}
