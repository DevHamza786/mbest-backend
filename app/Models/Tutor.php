<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Tutor extends Model
{
    use HasFactory;

    /**
     * `profile_complete` is maintained by syncProfileCompleteFlag(); not mass-assignable.
     */
    protected $fillable = [
        'user_id', 'department', 'specialization', 'hourly_rate',
        'subject_year_mapping', 'bio', 'qualifications', 'experience_years', 'is_available',
        'wwcc_number', 'wwcc_expiry_date', 'max_students_per_group',
    ];

    protected function casts(): array
    {
        return [
            'specialization' => 'array',
            'subject_year_mapping' => 'array',
            'hourly_rate' => 'decimal:2',
            'is_available' => 'boolean',
            'wwcc_expiry_date' => 'date',
            'profile_complete' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (Tutor $tutor) {
            $tutor->syncProfileCompleteFlag();
        });
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function classes()
    {
        return $this->hasMany(ClassModel::class);
    }

    public function sessions()
    {
        return $this->hasMany(TutoringSession::class, 'teacher_id');
    }

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }

    public function availability()
    {
        return $this->hasMany(TutorAvailability::class);
    }

    /**
     * Persist the cached completion flag (avoids recomputing rules on every read).
     * Pass $forUser when the triggering change was on the user row (fresh in memory).
     */
    public function syncProfileCompleteFlag(?User $forUser = null): void
    {
        $user = $forUser;
        if (!$user) {
            $this->loadMissing('user');
            $user = $this->user;
        }

        if (!$user) {
            if ($this->exists) {
                DB::table('tutors')->where('id', $this->id)->update([
                    'profile_complete' => false,
                    'updated_at' => now(),
                ]);
                $this->profile_complete = false;
            }

            return;
        }

        $complete = $this->profileIsCompleteForUser($user);

        if (!$this->exists) {
            return;
        }

        DB::table('tutors')->where('id', $this->id)->update([
            'profile_complete' => $complete,
            'updated_at' => now(),
        ]);

        $this->profile_complete = $complete;
        $this->syncOriginal();
    }

    /**
     * Tutor portal access: require phone, address, hourly rate, specializations with
     * year levels (subjects taught), WWCC number + valid expiry.
     */
    public function profileIsCompleteForUser(User $user): bool
    {
        $phoneOk = filled(trim((string) ($user->phone ?? '')));
        $addressOk = filled(trim((string) ($user->address ?? '')));

        $hourly = $this->hourly_rate;
        $hourlyOk = $hourly !== null && is_numeric($hourly) && (float) $hourly > 0;

        $specialization = is_array($this->specialization) ? $this->specialization : [];
        $subjectYearMapping = is_array($this->subject_year_mapping) ? $this->subject_year_mapping : [];

        $hasSpecialization = count($specialization) > 0;

        $hasSubjectYears = false;
        foreach ($specialization as $subject) {
            $years = $subjectYearMapping[$subject] ?? null;
            if (is_array($years) && count($years) > 0) {
                $hasSubjectYears = true;
                break;
            }
        }

        $wwccNumberOk = filled(trim((string) ($this->wwcc_number ?? '')));

        $expiry = $this->wwcc_expiry_date ? Carbon::parse($this->wwcc_expiry_date) : null;
        $wwccExpiryOk = $expiry !== null && $expiry->greaterThanOrEqualTo(Carbon::today());

        return $phoneOk
            && $addressOk
            && $hourlyOk
            && $hasSpecialization
            && $hasSubjectYears
            && $wwccNumberOk
            && $wwccExpiryOk;
    }

    public function getProfileCompletionDetails(?User $forUser = null): array
    {
        $user = $forUser ?? $this->user;
        if (!$user) {
            $this->loadMissing('user');
            $user = $this->user;
        }

        $phoneOk = $user ? filled(trim((string) ($user->phone ?? ''))) : false;
        $addressOk = $user ? filled(trim((string) ($user->address ?? ''))) : false;

        $hourly = $this->hourly_rate;
        $hourlyOk = $hourly !== null && is_numeric($hourly) && (float) $hourly > 0;

        $specialization = is_array($this->specialization) ? $this->specialization : [];
        $subjectYearMapping = is_array($this->subject_year_mapping) ? $this->subject_year_mapping : [];

        $hasSpecialization = count($specialization) > 0;
        $hasSubjectYears = false;
        foreach ($specialization as $subject) {
            $years = $subjectYearMapping[$subject] ?? null;
            if (is_array($years) && count($years) > 0) {
                $hasSubjectYears = true;
                break;
            }
        }

        $wwccNumberOk = filled(trim((string) ($this->wwcc_number ?? '')));
        $expiry = $this->wwcc_expiry_date ? Carbon::parse($this->wwcc_expiry_date) : null;
        $wwccExpiryOk = $expiry !== null && $expiry->greaterThanOrEqualTo(Carbon::today());

        return [
            'phone' => $phoneOk,
            'address' => $addressOk,
            'hourly_rate' => $hourlyOk,
            'specialization' => $hasSpecialization,
            'subject_year_mapping' => $hasSubjectYears,
            'wwcc_number' => $wwccNumberOk,
            'wwcc_expiry_date' => $wwccExpiryOk,
        ];
    }
}

