<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Сервис для работы с текстовыми сообщениями из JSON файла
 */
class LocaleService
{
    private array $texts = [];
    private string $filePath;

    public function __construct()
    {
        $this->filePath = __DIR__ . '/../../resources/locale.json';
        $this->loadTexts();
    }

    /**
     * Загрузка текстов из JSON файла
     */
    private function loadTexts(): void
    {
        try {
            if (!file_exists($this->filePath)) {
                Log::warning('Texts file not found', ['path' => $this->filePath]);
                return;
            }

            $content = file_get_contents($this->filePath);
            $decoded = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to decode texts JSON', [
                    'error' => json_last_error_msg()
                ]);
                return;
            }

            $this->texts = $decoded;
        } catch (\Exception $e) {
            Log::error('Error loading texts', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Получить текст по ключу
     *
     * @param string $key Ключ в формате "category.key" (например, "welcome.message")
     * @param array $replacements Массив замен для плейсхолдеров (например, ['{name}' => 'Иван'])
     * @return string
     */
    public function get(string $key, array $replacements = []): string
    {
        $keys = explode('.', $key);
        $value = $this->texts;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                Log::warning('Text key not found', ['key' => $key]);
                return $key;
            }
            $value = $value[$k];
        }

        if (!is_string($value)) {
            Log::warning('Text value is not a string', ['key' => $key]);
            return $key;
        }

        // Замена плейсхолдеров
        if (!empty($replacements)) {
            $value = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $value
            );
        }

        return $value;
    }

    /**
     * Получить всю категорию текстов
     *
     * @param string $category Название категории (например, "welcome")
     * @return array
     */
    public function getCategory(string $category): array
    {
        return $this->texts[$category] ?? [];
    }

    /**
     * Получить все тексты
     *
     * @return array
     */
    public function getAll(): array
    {
        return $this->texts;
    }

    /**
     * Перезагрузить тексты из файла
     */
    public function reload(): void
    {
        $this->texts = [];
        $this->loadTexts();
    }
}

