<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id', 'name', 'file_path', 'file_size', 'mime_type'
    ];

    // Relationships
    public function message()
    {
        return $this->belongsTo(Message::class);
    }
}

