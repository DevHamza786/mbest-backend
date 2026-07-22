<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AssignmentSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'submitted_at',
        'file_url',
        'text_submission',
        'link_submission',
        'status',
        'grade',
        'feedback',
        'graded_at',
        'student_comment',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
            'grade' => 'decimal:2',
        ];
    }

    /**
     * `file_url` is stored as a path relative to the public disk (e.g.
     * "assignments/submissions/xyz.pdf"), not an absolute URL - resolve it to one
     * so Linking.openURL (mobile) / a plain <a href> (web) actually works.
     */
    public function getFileUrlAttribute(?string $value): ?string
    {
        if (! $value) {
            return $value;
        }

        // Already an absolute URL (e.g. legacy rows) - leave it alone.
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        return url(Storage::disk('public')->url($value));
    }

    // Relationships
    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}

