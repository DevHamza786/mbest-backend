<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentFile extends Model
{
    use HasFactory;

    protected $table = 'assignment_files';

    protected $fillable = [
        'assignment_id',
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

    public function assignment()
    {
        return $this->belongsTo(Assignment::class, 'assignment_id');
    }
}

