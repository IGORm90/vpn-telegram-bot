<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VpnServer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'vpn_url',
        'title',
        'bearer_token',
        'country',
        'protocol',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'flag_emoji',
    ];
    
    /**
     * Получить emoji флага страны
     *
     * @return string
     */
    public function getFlagEmojiAttribute(): string
    {
        return $this->countryCodeToEmoji($this->country);
    }

    /**
     * Конвертировать код страны в emoji флага
     *
     * @param string $countryCode ISO 3166-1 alpha-2 код страны (например: US, GB, RU)
     * @return string
     */
    private function countryCodeToEmoji(string $countryCode): string
    {
        $countryCode = strtoupper($countryCode);
        
        if (strlen($countryCode) !== 2) {
            return '';
        }

        // Преобразуем двухбуквенный код страны в emoji флага
        // Региональные индикаторные символы начинаются с 0x1F1E6 (A) до 0x1F1FF (Z)
        $firstLetter = mb_chr(0x1F1E6 + ord($countryCode[0]) - ord('A'));
        $secondLetter = mb_chr(0x1F1E6 + ord($countryCode[1]) - ord('A'));

        return $firstLetter . $secondLetter;
    }

}
