<?php

namespace App\Services;

use App\Exceptions\TheMealDbException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TheMealDbClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $url = rtrim((string) config('services.themealdb.base_url'), '/');
        $key = (string) config('services.themealdb.api_key');

        $this->baseUrl = "{$url}/{$key}";
        $this->timeout = (int) config('services.themealdb.timeout', 15);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->retry(3, 500, throw: false);
    }

    public function getCategories(): array
    {
        $data = $this->get('/categories.php');

        if (!isset($data['categories']) || !is_array($data['categories'])) {
            return [];
        }

        return array_map(fn (array $item) => [
            'external_id' => $item['idCategory'] ?? null,
            'name'        => trim((string) ($item['strCategory'] ?? '')),
            'image_url'   => $item['strCategoryThumb'] ?? null,
            'description' => $item['strCategoryDescription'] ?? null,
        ], $data['categories']);
    }

    public function getMealsByCategory(string $category): array
    {
        $data = $this->get('/filter.php', ['c' => $category]);

        if (empty($data['meals']) || !is_array($data['meals'])) {
            return [];
        }

        return array_map(fn (array $meal) => [
            'external_meal_id' => (string) $meal['idMeal'],
            'title'            => trim((string) $meal['strMeal']),
            'image_url'        => $meal['strMealThumb'] ?? null,
        ], $data['meals']);
    }

    public function getMealById(string $mealId): ?array
    {
        $data = $this->get('/lookup.php', ['i' => $mealId]);

        if (empty($data['meals'][0]) || !is_array($data['meals'][0])) {
            return null;
        }

        $rawMeal = $data['meals'][0];

        return [
            'external_meal_id' => (string) $rawMeal['idMeal'],
            'title'            => trim((string) $rawMeal['strMeal']),
            'category'         => isset($rawMeal['strCategory']) ? trim((string) $rawMeal['strCategory']) : null,
            'instructions'     => trim((string) ($rawMeal['strInstructions'] ?? '')),
            'image_url'        => $rawMeal['strMealThumb'] ?? null,
            'ingredients'      => $this->extractIngredients($rawMeal),
        ];
    }

    public function getAllIngredients(): array
    {
        $data = $this->get('/list.php', ['i' => 'list']);

        if (empty($data['meals']) || !is_array($data['meals'])) {
            return [];
        }

        $ingredients = [];

        foreach ($data['meals'] as $item) {
            $name = trim((string) ($item['strIngredient'] ?? ''));

            if ($name !== '') {
                $ingredients[] = [
                    'external_id' => $item['idIngredient'] ?? null,
                    'name'        => $name,
                    'description' => $item['strDescription'] ?? null,
                ];
            }
        }

        return $ingredients;
    }

    private function extractIngredients(array $rawMeal): array
    {
        $parsed = [];

        for ($i = 1; $i <= 20; $i++) {
            $ingredient = trim((string) ($rawMeal["strIngredient{$i}"] ?? ''));
            $measure = trim((string) ($rawMeal["strMeasure{$i}"] ?? ''));

            if ($ingredient === '') {
                continue;
            }

            $parsed[] = [
                'name'        => $ingredient,
                'measure_raw' => $measure,
            ];
        }

        return $parsed;
    }

    private function get(string $endpoint, array $query = []): array
    {
        try {
            $response = $this->client()->get($endpoint, $query);
        } catch (Throwable $e) {
            Log::error('TheMealDB connection error', [
                'endpoint' => $endpoint,
                'query'    => $query,
                'message'  => $e->getMessage(),
            ]);

            throw new TheMealDbException('TheMealDB connection failed: ' . $e->getMessage(), 0, $e);
        }

        if ($response->failed()) {
            Log::warning('TheMealDB HTTP error', [
                'endpoint' => $endpoint,
                'status'   => $response->status(),
            ]);

            throw TheMealDbException::requestFailed($endpoint, $response->status());
        }

        $json = $response->json();

        if (!is_array($json)) {
            throw TheMealDbException::invalidPayload($endpoint);
        }

        return $json;
    }
}