<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminDashboard extends Model
{
    protected $fillable = [
        'name', 'integration_name', 'url', 'icon', 'description', 
        'color', 'usage_count', 'last_used', 'is_favorite'
    ];

    protected $casts = [
        'is_favorite' => 'boolean',
        'last_used' => 'datetime'
    ];

    public function credentials(): HasMany
    {
        return $this->hasMany(AdminCredential::class, 'dashboard_id');
    }

    public function defaultCredential()
    {
        return $this->hasOne(AdminCredential::class, 'dashboard_id')->where('is_default', true);
    }

    public function incrementUsage()
    {
        $this->increment('usage_count');
        $this->update(['last_used' => now()]);
    }
}