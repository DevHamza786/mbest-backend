<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResourceRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'description', 'category', 'type', 'priority',
        'status', 'requested_by', 'reviewed_by', 'review_notes', 'reviewed_at', 'resource_id', 'fulfilled_file'
    ];

    protected $appends = ['fulfilled_file_url'];

    public function getFulfilledFileUrlAttribute(): ?string
    {
        return $this->fulfilled_file ? \Illuminate\Support\Facades\Storage::url($this->fulfilled_file) : null;
    }

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    // Relationships
    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function resource()
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }
}

