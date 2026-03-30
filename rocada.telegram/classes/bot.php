<?php

namespace Rocada\Telegram;

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Web\HttpClient;

class TelegramBot
{
    private $botToken;
    private $apiUrl;
    public $crmId;

    public function __construct()
    {
        $this->botToken = Option::get('rocada.telegram', 'telegram_bot_token', '');
        $this->apiUrl = "https://api.telegram.org/bot" . $this->botToken . "/";
    }

    public function log($event, $data)
    {
		register_shutdown_function(function() use ($event, $data) {
			\CEventLog::Add([
				'SEVERITY' => 'SECURITY',
				'AUDIT_TYPE_ID' => $event,
				'MODULE_ID' => 'rocada.telegram',
				'ITEM_ID' => $this->crmId,
				'DESCRIPTION' => "Чат: " . $this->chatId . " | " . json_encode($data, JSON_UNESCAPED_UNICODE),
			]);
		});
    }

    private function sendRequest($method, $params = [])
    {
		static $last = 0;
		$now = microtime(true);
		$delta = $now - $last;
		if ($delta < 0.12) usleep((0.12 - $delta) * 1e6);
		$last = microtime(true);

        $httpClient = new HttpClient();

        $url = $this->apiUrl . $method;

        // Преобразуем параметры в формат multipart
        $response = $httpClient->post($url, $params); // Отправляем запрос

        if ($response === false) {
            throw new \Exception("Ошибка при отправке запроса: " . json_encode($httpClient->getError()));
        }

        $responseData = json_decode($response, true);

        $this->log("Отправка запроса $method к telegram.", "Параметры: " . json_encode($params) . " | Ответ: " . json_encode($responseData));

        return $responseData;
    }

    public function sendTextFile($chatId, $fileData, $filename = 'file.txt')
    {
        global $_SERVER;
        // Формируем уникальный boundary
        $boundary = '----WebKitFormBoundary' . md5(time());

        // Формируем тело запроса с файлом
        $data = "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"chat_id\"\r\n\r\n";
        $data .= $chatId . "\r\n";
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"document\"; filename=\"$filename\"\r\n";
        $data .= "Content-Type: application/octet-stream\r\n\r\n";
        $data .= $fileData . "\r\n";
        $data .= "--$boundary--\r\n";

        // Заголовки для отправки данных
        $headers = [
            "Content-Type: multipart/form-data; boundary=$boundary",
            "Content-Length: " . strlen($data)
        ];

        // URL для отправки запроса
        $url = 'https://api.telegram.org/bot' . $this->botToken . '/sendDocument';

        // Инициализация потока для отправки запроса
        $context = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", $headers),
                'content' => $data,
            ]
        ]);

        // Отправка запроса
        $response = file_get_contents($url, false, $context);

        $this->log("Отправка файла к telegram", "Параметры: " . json_encode($data) . " | Ответ: " . json_encode($response));

        // Обработка ответа
        return json_decode($response, true);
    }



    public function sendMessage($chatId, $text, $keyboard = null)
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'MARKDOWN',
        ];

        if ($keyboard) {
            // Здесь уже не нужно преобразовывать в JSON
            $params['reply_markup'] = json_encode($keyboard);
        }

        return $this->sendRequest('sendMessage', $params);
    }


    public function editMessage($chatId, $messageId, $newText, $keyboard = null)
    {
        $params = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $newText,
            'parse_mode' => 'MARKDOWN',
        ];

        if ($keyboard) {
            $params['reply_markup'] = json_encode($keyboard);
        }

        return $this->sendRequest('editMessageText', $params);
    }

    public function createKeyboard($buttons, $resize = true, $oneTime = false)
    {
        return [
            'keyboard' => $buttons,
            'resize_keyboard' => $resize,
            'one_time_keyboard' => $oneTime,
        ];
    }

    public function createInlineKeyboard($buttons)
    {
        return [
            'inline_keyboard' => $buttons,
        ];
    }
}
