<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'variant_id',
        'qty',
        'price_each'
    ];

    protected $casts = [
        'qty' => 'integer',
        'price_each' => 'decimal:2',
    ];

    // relationships a item belongs to a order
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // relationships a item has one variant
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    // Helper: calculate the subtotal
    public function getSubtotalAttribute() : float
    {
        return $this->qty * $this->price_each;
    }
}
