<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TutoringSessionFile extends Model
{
    use HasFactory;

    protected $table = 'tutoring_session_files';

    protected $fillable = [
        'session_id',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function session()
    {
        return $this->belongsTo(TutoringSession::class, 'session_id');
    }
}

