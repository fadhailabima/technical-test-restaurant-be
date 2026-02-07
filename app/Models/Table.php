<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $table_number
 * @property int $capacity
 * @property string $status
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 */
class Table extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_number',
        'capacity',
        'status',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($table) {
            if (!in_array($table->status, ['available', 'occupied', 'reserved'])) {
                $table->status = 'available';
            }
        });
    }

    public function orders()
    {
        return $this->hasManyThrough(Order::class, OrderSession::class);
    }

    public function orderSessions()
    {
        return $this->hasMany(OrderSession::class);
    }

    public function currentOrder()
    {
        return $this->hasManyThrough(Order::class, OrderSession::class)
            ->where('orders.status', 'open')
            ->latest();
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function markAsOccupied()
    {
        $this->update(['status' => 'occupied']);
    }

    public function markAsAvailable()
    {
        // Only mark as available if ALL sessions on this table are completed
        $hasActiveSessions = $this->orderSessions()
            ->where('status', 'active')
            ->exists();

        if (!$hasActiveSessions) {
            $this->update(['status' => 'available']);
        }
    }
}
