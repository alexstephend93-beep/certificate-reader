<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class DatabaseCredential extends Model
{
    protected $table = 'database_credentials';
    
    protected $fillable = [
        'name', 'connection_name', 'host', 'port', 'database', 
        'username', 'password', 'notes', 'is_active', 'is_default',
        'ssh_host',  // Added: link to SSH server
        'phpmyadmin_url'  // Added: phpMyAdmin URL
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'port' => 'integer'
    ];

    protected $attributes = [
        'connection_name' => 'mysql',
        'host' => '127.0.0.1',
        'port' => 3306,
        'is_active' => true,
        'is_default' => false
    ];

    // Encrypt password when setting
    public function setPasswordAttribute($value)
    {
        if ($value) {
            $this->attributes['password'] = Crypt::encryptString($value);
        } else {
            $this->attributes['password'] = null;
        }
    }

    // Decrypt password when accessing
    public function getDecryptedPasswordAttribute()
    {
        try {
            return $this->password ? Crypt::decryptString($this->password) : null;
        } catch (\Exception $e) {
            \Log::warning('Failed to decrypt password for database connection: ' . $this->name);
            return null;
        }
    }
    
    // Accessor for masked password display
    public function getMaskedPasswordAttribute()
    {
        return $this->password ? '••••••••' : null;
    }
    
    // Check if connection has password
    public function getHasPasswordAttribute()
    {
        return !empty($this->password);
    }
    
    // Scope for active connections only
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    // Scope for default connection
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
    
    // Get connection string for display
    public function getConnectionStringAttribute()
    {
        if ($this->connection_name === 'sqlite') {
            return "sqlite:{$this->database}";
        }
        
        return "{$this->connection_name}://{$this->username}@{$this->host}:{$this->port}/{$this->database}";
    }
    
    // Boot method to handle default connection logic
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            // If this is the first connection, make it default
            if (static::count() === 0) {
                $model->is_default = true;
            }
        });
        
        static::saving(function ($model) {
            // If setting as default, ensure no other defaults exist
            if ($model->is_default && $model->exists) {
                static::where('id', '!=', $model->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }
        });
        
        static::deleting(function ($model) {
            // If deleting default, assign new default
            if ($model->is_default) {
                $another = static::where('id', '!=', $model->id)->first();
                if ($another) {
                    $another->is_default = true;
                    $another->save();
                }
            }
        });
    }
}