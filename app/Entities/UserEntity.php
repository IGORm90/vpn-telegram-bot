<?php

namespace App\Entities;

use App\Models\User;
use App\Repositories\UserRepository;
use Carbon\Carbon;

/**
 * Синглтон-обёртка над моделью User
 * Предоставляет доступ к текущему пользователю из любой части приложения
 */
class UserEntity
{
    private static ?self $instance = null;

    private User $model;

    // Поля модели User
    public readonly int $id;
    public readonly int $telegramId;
    public readonly ?string $telegramUsername;
    public readonly ?int $vpnId;
    public readonly bool $isActive;
    public readonly ?Carbon $expiresAt;
    public readonly int $balance;
    public readonly array $settings;
    public readonly ?string $referralHash;
    public readonly ?string $referredByHash;
    public readonly ?Carbon $createdAt;
    public readonly ?Carbon $updatedAt;

    /**
     * Приватный конструктор для синглтона
     */
    private function __construct(User $user)
    {
        $this->model = $user;
        $this->hydrate();
    }

    /**
     * Инициализация синглтона
     */
    public static function init(int $telegramId, ?string $telegramUsername): self
    {
        if (self::$instance === null) {
            $repository = app(UserRepository::class);
            $user = $repository->getOrCreate($telegramId, $telegramUsername);
            self::$instance = new self($user);
        }

        return self::$instance;
    }

    /**
     * Получить экземпляр синглтона
     *
     * @throws \RuntimeException если синглтон не был инициализирован
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('UserEntity не инициализирован. Сначала вызовите UserEntity::init()');
        }

        return self::$instance;
    }

    public static function getUserTelegramId(): ?int
    {
        return self::$instance?->telegramId;
    }

    /**
     * Проверить, инициализирован ли синглтон
     */
    public static function isInitialized(): bool
    {
        return self::$instance !== null;
    }

    /**
     * Сбросить синглтон (для тестов или при необходимости переинициализации)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Получить оригинальную модель User
     */
    public function getModel(): User
    {
        return $this->model;
    }

    /**
     * Обновить данные из модели
     */
    public function refresh(): self
    {
        $this->model->refresh();
        $this->hydrate();

        return $this;
    }

    /**
     * Заполнить свойства из модели
     */
    private function hydrate(): void
    {
        $this->id = $this->model->id;
        $this->telegramId = $this->model->telegram_id;
        $this->telegramUsername = $this->model->telegram_username;
        $this->vpnId = $this->model->vpn_id;
        $this->isActive = $this->model->is_active;
        $this->expiresAt = $this->model->expires_at;
        $this->balance = $this->model->balance ?? 0;
        $this->settings = $this->model->settings ?? [];
        $this->referralHash = $this->model->referral_hash;
        $this->referredByHash = $this->model->referred_by_hash;
        $this->createdAt = $this->model->created_at;
        $this->updatedAt = $this->model->updated_at;
    }
}
