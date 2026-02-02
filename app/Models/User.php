<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Package;
use App\Models\Payment;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name', 'email', 'password', 'role', 'avatar',
        'phone', 'date_of_birth', 'address', 'is_active',
        'package_id', 'subscription_status', 'subscription_approved_at',
        'current_student_count', 'current_course_count'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'subscription_status' => 'string',
            'subscription_approved_at' => 'datetime',
            'current_student_count' => 'integer',
            'current_course_count' => 'integer',
        ];
    }

    // Relationships
    public function tutor()
    {
        return $this->hasOne(Tutor::class);
    }

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function parentModel()
    {
        return $this->hasOne(ParentModel::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'recipient_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'parent_id');
    }

    /**
     * Check if user has reached student limit
     */
    public function hasReachedStudentLimit(): bool
    {
        if (!$this->package) {
            return true; // No package = limit reached
        }
        return $this->current_student_count >= $this->package->student_limit;
    }


    /**
     * Check if user's package includes a specific class
     */
    public function packageIncludesClass(int $classId): bool
    {
        if (!$this->package) {
            return false;
        }
        return $this->package->classes()->where('classes.id', $classId)->exists();
    }

    /**
     * Check if user can request 1:1 sessions
     */
    public function canRequestOneOnOne(): bool
    {
        if (!$this->package) {
            return false;
        }
        return $this->package->allows_one_on_one && $this->subscription_status === 'active';
    }

    /**
     * Get the avatar URL attribute
     */
    public function getAvatarUrlAttribute()
    {
        if (!$this->avatar) {
            return null;
        }
        return Storage::url($this->avatar);
    }
}
