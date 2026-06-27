<?php

class TelegramBot {
    private string $token;
    private string $apiUrl;
    private int $lastUpdateId = 0;

    public function __construct(string $token) {
        $this->token = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$token}";
    }

    public function call(string $method, array $data = []): ?array {
        $url = $this->apiUrl . '/' . $method;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 35,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            appLog("Telegram API error: $error", 'ERROR');
            return null;
        }

        $result = json_decode($response, true);
        if (!$result || !($result['ok'] ?? false)) {
            appLog("Telegram API: " . ($result['description'] ?? 'unknown response'), 'ERROR');
            return null;
        }

        return $result['result'] ?? null;
    }

    public function getUpdates(int $timeout = 30): ?array {
        $data = [
            'offset' => $this->lastUpdateId + 1,
            'timeout' => $timeout,
            'allowed_updates' => json_encode(['message']),
        ];
        return $this->call('getUpdates', $data);
    }

    public function sendMessage(int $chatId, string $text, string $parseMode = 'HTML'): ?array {
        return $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
            'disable_web_page_preview' => true,
        ]);
    }

    public function sendChatAction(int $chatId, string $action = 'typing'): void {
        $this->call('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action,
        ]);
    }

    public function setLastUpdateId(int $id): void {
        $this->lastUpdateId = $id;
    }

    public function getLastUpdateId(): int {
        return $this->lastUpdateId;
    }
}
