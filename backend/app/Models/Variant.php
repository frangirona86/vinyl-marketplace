<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Variant extends Model
{
    protected $fillable = [
        'record_id',
        'artist_id',
        'condition',
        'color',
        'price',
        'stock',
        'photos',
    ];

    protected $casts = [
        'photos' => 'array',
        'price' => 'decimal:2',
        'stock' => 'integer',
    ];

    public function record(): BelongsTo
    {
        return $this->belongsTo(Record::class);
    }

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
