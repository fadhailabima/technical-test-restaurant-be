<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderSession extends Model
{
    protected $fillable = [
        'table_id',
        'customer_name',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function getLatestOrderAttribute()
    {
        return $this->orders()->orderBy('created_at', 'desc')->first();
    }

    public function getTotalAmountAttribute()
    {
        return $this->orders()->sum('total');
    }

    public function hasUnpaidOrders(): bool
    {
        return $this->orders()->where('payment_status', 'unpaid')->exists();
    }

    public function allOrdersClosed(): bool
    {
        return !$this->orders()->whereNotIn('status', ['closed', 'cancelled'])->exists();
    }
}
