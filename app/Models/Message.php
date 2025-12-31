<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_id', 'sender_id', 'recipient_id', 'subject',
        'body', 'is_read', 'is_important', 'read_at'
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'is_important' => 'boolean',
            'read_at' => 'datetime',
        ];
    }

    // Relationships
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class);
    }
}

