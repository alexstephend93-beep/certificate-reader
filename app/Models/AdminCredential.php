<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class AdminCredential extends Model
{
    protected $fillable = [
        'dashboard_id', 'email', 'username', 'password', 'role', 
        'notes', 'is_active', 'is_default', 'usage_count', 'last_used'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'last_used' => 'datetime'
    ];

    public function setPasswordAttribute($value)
    {
        $this->attributes['password'] = Crypt::encryptString($value);
    }

    public function getPasswordAttribute($value)
    {
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            return '*** Encrypted ***';
        }
    }

    public function dashboard()
    {
        return $this->belongsTo(AdminDashboard::class, 'dashboard_id');
    }

    public function incrementUsage()
    {
        $this->increment('usage_count');
        $this->update(['last_used' => now()]);
    }
}