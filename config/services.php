// config/services.php
return [
    // ... інші сервіси
    'themealdb' => [
        'base_url' => env('THEMEALDB_BASE_URL', 'https://www.themealdb.com/api/json/v1'),
        'api_key' => env('THEMEALDB_API_KEY', '1'), // Відкритий тестовий ключ
        'timeout' => 15,
    ],
];