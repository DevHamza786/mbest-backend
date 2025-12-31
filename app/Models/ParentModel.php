<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentModel extends Model
{
    use HasFactory;

    protected $table = 'parents';

    protected $fillable = [
        'user_id', 'relationship'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function students()
    {
        // parent_id in pivot table references users.id (not parents.id)
        // So we query students where parent_student.parent_id = this->user_id
        return Student::whereHas('parents', function($query) {
            $query->where('users.id', $this->user_id);
        });
    }
}

