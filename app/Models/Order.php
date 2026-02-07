<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $order_number
 * @property int $order_session_id
 * @property int $waiter_id
 * @property int|null $cashier_id
 * @property string $status
 * @property string $payment_status
 * @property string $subtotal
 * @property string $tax
 * @property string $total
 * @property \Carbon\Carbon $opened_at
 * @property \Carbon\Carbon|null $closed_at
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'order_session_id',
        'waiter_id',
        'cashier_id',
        'status',
        'payment_status',
        'subtotal',
        'tax',
        'total',
        'opened_at',
        'closed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    protected $with = ['orderSession.table', 'waiter'];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($order) {
            $validStatuses = ['open', 'preparing', 'ready', 'served', 'closed', 'cancelled'];
            if (!in_array($order->status, $validStatuses)) {
                $order->status = 'open';
            }

            $validPaymentStatuses = ['unpaid', 'paid'];
            if (isset($order->payment_status) && !in_array($order->payment_status, $validPaymentStatuses)) {
                $order->payment_status = 'unpaid';
            }
        });
    }

    public function orderSession()
    {
        return $this->belongsTo(OrderSession::class);
    }

    public function table()
    {
        return $this->hasOneThrough(Table::class, OrderSession::class, 'id', 'id', 'order_session_id', 'table_id');
    }

    public function waiter()
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function calculateTotals()
    {
        $subtotal = $this->items()->sum('subtotal');
        $taxPercentage = config('restaurant.tax_percentage', 10);
        $tax = $subtotal * ($taxPercentage / 100);
        $total = $subtotal + $tax;

        $this->update([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
        ]);
    }

    public static function generateOrderNumber()
    {
        $date = now()->format('Ymd');
        $lastOrder = self::whereDate('created_at', now())->latest('id')->first();
        $sequence = $lastOrder ? (intval(substr($lastOrder->order_number, -4)) + 1) : 1;

        return $date . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    // Attribute accessors for backward compatibility
    public function getTableAttribute()
    {
        return $this->orderSession ? $this->orderSession->table : null;
    }

    public function getCustomerNameAttribute()
    {
        return $this->orderSession ? $this->orderSession->customer_name : null;
    }

    public function getTableIdAttribute()
    {
        return $this->orderSession ? $this->orderSession->table_id : null;
    }
}
