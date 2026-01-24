<?php

namespace App\Exceptions;

use Exception;

class PaymentException extends Exception
{
    private ?int $chatId;
    private string $userMessage;
    private array $context;

    public function __construct(
        string $message,
        ?int $chatId = null,
        string $userMessage = 'Произошла ошибка при обработке платежа. Обратитесь в поддержку.',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);

        $this->chatId = $chatId;
        $this->userMessage = $userMessage;
        $this->context = $context;
    }

    public function getChatId(): ?int
    {
        return $this->chatId;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function missingPreCheckoutQuery(array $update): self
    {
        return new self(
            'Missing pre_checkout_query in update',
            null,
            'Ошибка данных платежа. Обратитесь в поддержку.',
            ['update' => $update]
        );
    }

    public static function missingPreCheckoutFields(?string $queryId, ?string $payload): self
    {
        return new self(
            'Missing required fields in pre_checkout_query',
            null,
            'Отсутствуют обязательные данные платежа.',
            [
                'queryId' => $queryId,
                'payload' => $payload,
            ]
        );
    }

    public static function missingSuccessfulPayment(array $update): self
    {
        return new self(
            'Missing successful_payment in message',
            null,
            'Ошибка данных платежа. Обратитесь в поддержку.',
            ['update' => $update]
        );
    }

    public static function missingRequiredFields(?string $payload, ?string $telegramChargeId): self
    {
        return new self(
            'Missing required fields in successful_payment',
            null,
            'Отсутствуют обязательные данные платежа. Обратитесь в поддержку.',
            [
                'payload' => $payload,
                'telegramChargeId' => $telegramChargeId,
            ]
        );
    }

    public static function invoiceNotFound(string $payload, ?int $chatId): self
    {
        return new self(
            'Invoice not found for payload',
            $chatId,
            'Счёт не найден. Обратитесь в поддержку.',
            ['payload' => $payload]
        );
    }

    public static function payloadMismatch(string $expected, string $received, ?int $chatId): self
    {
        return new self(
            'Payload mismatch in successful_payment',
            $chatId,
            'Ошибка payload. Обратитесь в поддержку.',
            [
                'expected' => $expected,
                'received' => $received,
            ]
        );
    }

    public static function currencyMismatch(string $expected, string $received, string $payload, ?int $chatId): self
    {
        return new self(
            'Currency mismatch in successful_payment',
            $chatId,
            'Ошибка валюты. Обратитесь в поддержку.',
            [
                'expected' => $expected,
                'received' => $received,
                'payload' => $payload,
            ]
        );
    }

    public static function amountMismatch(int $expected, int $received, string $payload, ?int $chatId): self
    {
        return new self(
            'Amount mismatch in successful_payment',
            $chatId,
            'Ошибка суммы. Обратитесь в поддержку.',
            [
                'expected' => $expected,
                'received' => $received,
                'payload' => $payload,
            ]
        );
    }
}
