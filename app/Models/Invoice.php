<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number', 'student_id', 'parent_id', 'tutor_id', 'session_id',
        'amount', 'currency', 'status', 'due_date', 'paid_date',
        'issue_date', 'period_start', 'period_end', 'description',
        'tutor_address', 'notes', 'payment_method', 'transaction_id'
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_date' => 'datetime',
            'issue_date' => 'date',
            'period_start' => 'date',
            'period_end' => 'date',
        ];
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_id');
    }

    public function tutor()
    {
        return $this->belongsTo(Tutor::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function session()
    {
        return $this->belongsTo(TutoringSession::class, 'session_id');
    }
}
