<?php

namespace App\Models;

// Keep the standard Laravel User model traits/fillable fields if merging into an existing app.
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'download_quota'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    // Tambahkan fungsi relasi ini di bagian bawah class User:
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
