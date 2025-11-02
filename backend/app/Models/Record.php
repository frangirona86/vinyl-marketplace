<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Record extends Model
{
    protected $fillable = [
        'title',
        'artist_id',
        'artist_name',
        'label',
        'genre',
        'year',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'year' => 'integer',
    ];

    // Relationships: a record belongs to an artist
    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }
}
