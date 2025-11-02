<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'total_price',
        'status',
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
    ];

    // relationships a order belongs to a user
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // relationships a order has many order items
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
