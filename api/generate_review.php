<?php
require __DIR__ . '/../vendor/autoload.php';

#Конфигурация заголовка
header('Content-Type: application/json; charset=utf-8');

#Берем API-клюс из .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..'); 
$dotenv->load(); 

$apiKey = $_ENV['OPENAI_API_KEY'] ?? null;  
if(!$apiKey){
    http_response_code(500);
    echo json_encode(['error' => 'OPENAI_API_KEY не найден в окружении/.env'], JSON_UNESCAPED_UNICODE);
    exit;
}


function get_request_data(): array{
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if($data === null){
        http_response_code(400);
        echo json_encode(['error' => 'Некорректный ввод'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!isset($data['name']) || !isset($data['description']) || !isset($data['rating'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Отсутствуют обязательные поля'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $name = trim((string)$data['name']);
    $description = trim((string)$data['description']);
    $rating = (int)$data['rating'];

    if ($name === '' || $description === '') {
        http_response_code(400);
        echo json_encode(['error' => 'name/description не могут быть пустыми'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['error' => 'rating должен быть целым числом от 1 до 5'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $low_limit = $data['low_limit'] ?? 0;
    $up_limit  = $data['up_limit'] ?? 50;

    $low_limit = (int)$low_limit;
    $up_limit  = (int)$up_limit;

    if ($low_limit < 0 || $up_limit > 500 || $low_limit > $up_limit) {
        http_response_code(400);
        echo json_encode(['error' => 'Неверные пределы low_limit/up_limit'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return [
        'name'        => $name,
        'description' => $description,
        'rating'      => $rating,
        'low_limit'   => $low_limit,
        'up_limit'    => $up_limit,
    ];
      
}

#создать запрос для удаленного llm-провайдера
function build_payload_gpt4o_mini(string $name, string $description, int $rating, int $low_limit, int $up_limit): array{
    $prompt = <<<PROMPT
Сгенерируй реалистичный отзыв на товар "$name".
Описание: $description.
Оценка: $rating из 5.
Пиши на русском языке, длиной от $low_limit до $up_limit слов, с плюсами и минусами.
PROMPT;
    return [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'Ты помогаешь писать правдоподобные отзывы о товарах.'],
            ['role' => 'user',   'content' => $prompt]
        ],
        'temperature' => 0.7,
        ];
    
}

#отправить запрос на удаленный llm-провайдер
function call_gpt4o_mini(array $payload, string $apiKey): string{
    $url = 'https://api.openai.com/v1/chat/completions';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ],
    CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30
    ]);
    $response = curl_exec($ch);
    if($response === false){
        throw new Exception('Ошибка запроса: '.curl_error($ch));
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if($status < 200 || $status >= 300){
        throw new Exception("Сервер вернул код $status: $response");
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? 'Пустой ответ от AI';
}

#основная программа
try{
    $request = get_request_data();
    $payload = build_payload_gpt4o_mini($request['name'], $request['description'], $request['rating'], $request['low_limit'], $request['up_limit']);
    $result = call_gpt4o_mini($payload, $apiKey);

    echo json_encode(['review' => trim($result)], JSON_UNESCAPED_UNICODE);
}catch(Throwable $e){
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}


