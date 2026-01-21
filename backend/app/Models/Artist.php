<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Artist extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'display_name', 'bio', 'avatar_url'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }
}
