<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * HTTP Service для выполнения запросов к внешним API
 */
class HttpService
{
    private Client $client;
    private array $defaultHeaders;

    /**
     * @param array $config Конфигурация для Guzzle Client
     */
    public function __construct(array $config = [])
    {
        $defaultConfig = [
            'timeout' => 30,
            'connect_timeout' => 10,
            'verify' => true,
        ];

        $this->client = new Client(array_merge($defaultConfig, $config));
        $this->defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Установить заголовки по умолчанию
     *
     * @param array $headers
     * @return self
     */
    public function setDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * GET запрос
     *
     * @param string $url
     * @param array $query Query параметры
     * @param array $headers Дополнительные заголовки
     * @return array|null
     */
    public function get(string $url, array $query = [], array $headers = []): ?array
    {
        return $this->request('GET', $url, [
            'query' => $query,
            'headers' => array_merge($this->defaultHeaders, $headers),
        ]);
    }

    /**
     * POST запрос
     *
     * @param string $url
     * @param array $data Данные для отправки
     * @param array $headers Дополнительные заголовки
     * @return array|null
     */
    public function post(string $url, array $data = [], array $headers = []): ?array
    {
        return $this->request('POST', $url, [
            'json' => $data,
            'headers' => array_merge($this->defaultHeaders, $headers),
        ]);
    }

    /**
     * PUT запрос
     *
     * @param string $url
     * @param array $data Данные для отправки
     * @param array $headers Дополнительные заголовки
     * @return array|null
     */
    public function put(string $url, array $data = [], array $headers = []): ?array
    {
        return $this->request('PUT', $url, [
            'json' => $data,
            'headers' => array_merge($this->defaultHeaders, $headers),
        ]);
    }

    /**
     * PATCH запрос
     *
     * @param string $url
     * @param array $data Данные для отправки
     * @param array $headers Дополнительные заголовки
     * @return array|null
     */
    public function patch(string $url, array $data = [], array $headers = []): ?array
    {
        return $this->request('PATCH', $url, [
            'json' => $data,
            'headers' => array_merge($this->defaultHeaders, $headers),
        ]);
    }

    /**
     * DELETE запрос
     *
     * @param string $url
     * @param array $query Query параметры
     * @param array $headers Дополнительные заголовки
     * @return array|null
     */
    public function delete(string $url, array $query = [], array $headers = []): ?array
    {
        return $this->request('DELETE', $url, [
            'query' => $query,
            'headers' => array_merge($this->defaultHeaders, $headers),
        ]);
    }

    /**
     * POST запрос с multipart/form-data (для отправки файлов)
     *
     * @param string $url
     * @param array $multipart Массив multipart данных
     * @param array $headers Дополнительные заголовки
     * @return array|null
     */
    public function postMultipart(string $url, array $multipart = [], array $headers = []): ?array
    {
        // Удаляем Content-Type из заголовков, Guzzle установит его автоматически для multipart
        $customHeaders = array_merge($this->defaultHeaders, $headers);
        unset($customHeaders['Content-Type']);

        return $this->request('POST', $url, [
            'multipart' => $multipart,
            'headers' => $customHeaders,
        ]);
    }

    /**
     * Универсальный метод для выполнения HTTP запросов
     *
     * @param string $method HTTP метод
     * @param string $url URL адрес
     * @param array $options Опции запроса
     * @return array|null
     */
    public function request(string $method, string $url, array $options = []): ?array
    {
        try {
            $response = $this->client->request($method, $url, $options);
            $body = $response->getBody()->getContents();

            return [
                'success' => true,
                'status' => $response->getStatusCode(),
                'data' => json_decode($body, true) ?? $body,
                'headers' => $response->getHeaders(),
            ];
        } catch (GuzzleException $e) {
            Log::error('HTTP Request Error', [
                'method' => $method,
                'url' => $url,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return [
                'success' => false,
                'status' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Получить экземпляр Guzzle Client
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return $this->client;
    }
}

