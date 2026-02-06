<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $order_id
 * @property string $payment_method
 * @property string $amount
 * @property string $status
 * @property string|null $reference_number
 * @property string|null $notes
 * @property \Carbon\Carbon|null $paid_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'status',
        'reference_number',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            $validMethods = ['cash', 'card', 'qris', 'gopay', 'ovo', 'dana'];
            if (!in_array($payment->payment_method, $validMethods)) {
                throw new \InvalidArgumentException('Invalid payment method');
            }

            $validStatuses = ['pending', 'completed', 'failed', 'refunded'];
            if (!in_array($payment->status, $validStatuses)) {
                throw new \InvalidArgumentException('Invalid payment status');
            }
        });
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'paid_at' => now(),
        ]);
    }
}
