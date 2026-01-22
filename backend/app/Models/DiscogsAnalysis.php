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
        "styles",
        "format",
        "have",
        "want",
        "rating_average",
        "rating_count",
        "num_for_sale",
        "lowest_price",
        "lowest_price_currency",
        "recommended_price_min",
        "recommended_price_max",
        "price_suggestions",
        "demand_ratio",
        "ai_score",
        "ai_recommendation",
        "ai_analysis",
        "is_rare",
        "is_in_demand",
        "raw_data",
        "previous_have",
        "previous_want",
        "previous_lowest_price",
        "cover_image",
        "thumb",
        "notes",
        "is_watchlist",
        "fetched_at",
        "last_refreshed_at",
    ];

    protected $casts = [
        "genres" => "array",
        "styles" => "array",
        "price_suggestions" => "array",
        "raw_data" => "array",
        "is_rare" => "boolean",
        "is_in_demand" => "boolean",
        "is_watchlist" => "boolean",
        "fetched_at" => "datetime",
        "last_refreshed_at" => "datetime",
        "demand_ratio" => "decimal:4",
        "lowest_price" => "decimal:2",
        "recommended_price_min" => "decimal:2",
        "recommended_price_max" => "decimal:2",
        "previous_lowest_price" => "decimal:2",
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

    public function scopeByStyle($query, $style)
    {
        return $query->whereJsonContains("styles", $style);
    }

    public function scopeByYear($query, $year)
    {
        return $query->where("year", $year);
    }

    public function scopeByYearRange($query, $from, $to)
    {
        return $query->whereBetween("year", [$from, $to]);
    }

    public function scopeByCountry($query, $country)
    {
        return $query->where("country", $country);
    }

    public function scopeByLabel($query, $label)
    {
        return $query->where("label", "like", "%{$label}%");
    }

    public function scopeByFormat($query, $format)
    {
        return $query->where("format", $format);
    }

    public function scopeHighScoring($query, $minScore = 70)
    {
        return $query->where("ai_score", ">=", $minScore);
    }

    public function scopeRecommendedBuy($query)
    {
        return $query->where("ai_recommendation", "BUY");
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

    // Get image URL with fallback
    public function getImageAttribute(): ?string
    {
        return $this->thumb ?? $this->cover_image;
    }

    // Get high-res image URL with fallback
    public function getImageLargeAttribute(): ?string
    {
        return $this->cover_image ?? $this->thumb;
    }

    // Check if has image
    public function hasImage(): bool
    {
        return !empty($this->thumb) || !empty($this->cover_image);
    }

    // Check if trending (significant changes since last refresh)
    public function isTrending(): bool
    {
        if (!$this->previous_have || !$this->previous_want) {
            return false;
        }

        $wantChange = $this->want - $this->previous_want;
        $wantChangePercent = $this->previous_want > 0 
            ? ($wantChange / $this->previous_want) * 100 
            : 0;

        // Trending if want increased by more than 10%
        return $wantChangePercent > 10;
    }

    // Get changes summary
    public function getChangesAttribute(): ?array
    {
        if (!$this->previous_have && !$this->previous_want && !$this->previous_lowest_price) {
            return null;
        }

        return [
            'have_change' => $this->previous_have ? $this->have - $this->previous_have : null,
            'want_change' => $this->previous_want ? $this->want - $this->previous_want : null,
            'price_change' => $this->previous_lowest_price && $this->lowest_price 
                ? $this->lowest_price - $this->previous_lowest_price 
                : null,
            'is_trending' => $this->isTrending(),
        ];
    }

    // Scope for trending items
    public function scopeTrending($query)
    {
        return $query->whereNotNull('previous_want')
            ->whereRaw('want > previous_want * 1.1'); // 10% increase
    }

    // Scope for items needing refresh
    public function scopeNeedsRefresh($query, $hours = 24)
    {
        return $query->where(function ($q) use ($hours) {
            $q->whereNull('last_refreshed_at')
              ->orWhere('last_refreshed_at', '<', now()->subHours($hours));
        });
    }
}
