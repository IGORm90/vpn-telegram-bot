<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StarInvoice extends Model
{
    use HasFactory;
    protected $table = 'star_invoices';
    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'user_id',
        'telegram_username',
        'amount',
        'currency',
        'status',
        'payload',
        'telegram_payment_charge_id',
        'provider_payment_charge_id',
        'payment_method',
        'transaction_id',
        'metadata',
        'raw_pre_checkout_query',
        'raw_successful_payment',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'user_id' => 'integer',
        'amount' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Получить пользователя транзакции
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

}
