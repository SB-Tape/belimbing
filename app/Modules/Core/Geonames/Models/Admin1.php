<?php

// SPDX-License-Identifier: AGPL-3.0-only
// (c) Ng Kiat Siong <kiatsiong.ng@gmail.com>

namespace App\Modules\Core\Geonames\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Admin1 extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'geonames_admin1';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = ['code', 'name', 'alt_name'];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Extract the country ISO code from the code field (e.g., 'US.CA' → 'US').
     */
    public function getCountryIsoAttribute(): ?string
    {
        if (! $this->code) {
            return null;
        }

        return explode('.', $this->code)[0] ?? null;
    }

    /**
     * Scope: filter by country ISO code prefix.
     */
    public function scopeForCountry(Builder $query, string $iso): Builder
    {
        return $query->whereRaw('UPPER(code) LIKE ?', [strtoupper($iso).'.%']);
    }

    /**
     * Scope: join geonames_countries to add country_name to the result set.
     */
    public function scopeWithCountryName(Builder $query): Builder
    {
        return $query
            ->selectRaw('geonames_admin1.*, geonames_countries.country as country_name, geonames_countries.iso as country_iso')
            ->leftJoin('geonames_countries', function ($join) {
                $join->whereRaw("geonames_countries.iso = SPLIT_PART(geonames_admin1.code, '.', 1)");
            });
    }
}
