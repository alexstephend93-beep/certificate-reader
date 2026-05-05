<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Command extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'command',
        'description',
        'alternate_commands',
        'example_usage',
        'notes',
        'tags',
        'os',
        'usage_count',
        'is_favorite',
        'icon'
    ];

    protected $casts = [
        'alternate_commands' => 'array',
        'is_favorite' => 'boolean',
        'usage_count' => 'integer'
    ];

    // Scope for searching
    public function scopeSearch($query, $search)
    {
        return $query->where('name', 'LIKE', "%{$search}%")
            ->orWhere('command', 'LIKE', "%{$search}%")
            ->orWhere('description', 'LIKE', "%{$search}%")
            ->orWhere('tags', 'LIKE', "%{$search}%");
    }

    // Scope by category
    public function scopeCategory($query, $category)
    {
        if ($category && $category !== 'all') {
            return $query->where('category', $category);
        }
        return $query;
    }

    // Increment usage count
    public function incrementUsage()
    {
        $this->increment('usage_count');
    }
}