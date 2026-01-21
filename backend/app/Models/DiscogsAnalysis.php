<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscogsAnalysis extends Model
{
    use HasFactory;

    protected $fillable = [
        "discogs_id",
        "title",
        "artist_name",
        "year",
        "country",
        "label",
        "catalog_number",
        "genres",
        "format",
        "have",
        "want",
        "rating_average",
        "rating_count",
        "num_for_sale",
        "lowest_price",
        "lowest_price_currency",
        "price_suggestions",
        "demand_ratio",
        "is_rare",
        "is_in_demand",
        "raw_data",
        "cover_image",
        "notes",
        "is_watchlist",
        "fetched_at",
    ];

    protected $casts = [
        "genres" => "array",
        "price_suggestions" => "array",
        "raw_data" => "array",
        "is_rare" => "boolean",
        "is_in_demand" => "boolean",
        "is_watchlist" => "boolean",
        "fetched_at" => "datetime",
        "demand_ratio" => "decimal:4",
        "lowest_price" => "decimal:2",
        "rating_average" => "decimal:2",
    ];

    // Scopes for common queries
    public function scopeRare($query)
    {
        return $query->where("is_rare", true);
    }

    public function scopeInDemand($query)
    {
        return $query->where("is_in_demand", true);
    }

    public function scopeWatchlist($query)
    {
        return $query->where("is_watchlist", true);
    }

    public function scopeWithHighDemand($query, $minRatio = 1.0)
    {
        return $query->where("demand_ratio", ">=", $minRatio);
    }

    public function scopeUnderPrice($query, $maxPrice)
    {
        return $query->where("lowest_price", "<=", $maxPrice);
    }

    public function scopeByArtist($query, $artist)
    {
        return $query->where("artist_name", "like", "%{$artist}%");
    }

    public function scopeByGenre($query, $genre)
    {
        return $query->whereJsonContains("genres", $genre);
    }

    // Calculate demand ratio
    public function calculateDemandRatio(): float
    {
        if ($this->have == 0) {
            return $this->want > 0 ? 999.99 : 0;
        }
        return round($this->want / $this->have, 4);
    }

    // Check if needs refresh (older than 24 hours)
    public function needsRefresh(): bool
    {
        if (!$this->fetched_at) {
            return true;
        }
        return $this->fetched_at->diffInHours(now()) > 24;
    }

    // Format for display
    public function getFormattedPriceAttribute(): ?string
    {
        if (!$this->lowest_price) {
            return null;
        }
        return number_format($this->lowest_price, 2) . " " . ($this->lowest_price_currency ?? "USD");
    }

    public function getDemandStatusAttribute(): string
    {
        if ($this->demand_ratio >= 2) {
            return "Very High Demand";
        } elseif ($this->demand_ratio >= 1) {
            return "High Demand";
        } elseif ($this->demand_ratio >= 0.5) {
            return "Moderate Demand";
        }
        return "Low Demand";
    }
}
