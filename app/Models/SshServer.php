<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SshServer extends Model
{
    protected $fillable = [
        'host',
        'hostname',
        'user',
        'identity_file',
        'port',
        'domains',
        'description',
        'is_favorite',
        'last_connected_at',
    ];

    protected $casts = [
        'domains' => 'array',
        'is_favorite' => 'boolean',
        'port' => 'integer',
        'last_connected_at' => 'datetime',
    ];

    public function scopeFavorites($query)
    {
        return $query->where('is_favorite', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('is_favorite', 'desc')->orderBy('host', 'asc');
    }
}

