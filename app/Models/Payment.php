<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'package_id',
        'amount',
        'payment_slip_path',
        'status',
        'admin_notes',
        'approved_by',
        'approved_at',
    ];

    protected $appends = ['payment_slip_url'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    // Relationships
    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the payment slip URL
     */
    public function getPaymentSlipUrlAttribute()
    {
        if (!$this->payment_slip_path) {
            return null;
        }
        return Storage::url($this->payment_slip_path);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
