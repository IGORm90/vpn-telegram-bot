<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'telegram_id',
        'telegram_username',
        'vpn_id',
        'is_active',
        'expires_at',
        'settings',
        'balance',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'telegram_id' => 'integer',
        'vpn_id' => 'integer',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'settings' => 'array',
        'balance' => 'integer',
    ];

    /**
     * Получить транзакции пользователя
     */
    public function starInvoices(): HasMany
    {
        return $this->hasMany(StarInvoice::class);
    }
}
