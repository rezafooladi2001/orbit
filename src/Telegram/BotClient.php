<?php

declare(strict_types=1);

namespace Ghidar\Telegram;

use Ghidar\Config\Config;

/**
 * Telegram Bot API client wrapper.
 * Provides methods for interacting with Telegram Bot API.
 */
class BotClient
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? Config::get('TELEGRAM_BOT_TOKEN', '');
        $this->baseUrl = 'https://api.telegram.org/bot' . $this->apiKey . '/';
    }

    /**
     * Call Telegram Bot API method.
     *
     * @param string $method API method name
     * @param array<string, mixed> $data Request data
     * @return object|null API response
     */
    public function call(string $method, array $data = []): ?object
    {
        $url = $this->baseUrl . $method;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $res = curl_exec($ch);

        // Check for cURL errors
        if (curl_error($ch)) {
            $error = curl_error($ch);
            // No need to call curl_close() in PHP 8.0+; handle is auto-closed by garbage collector
            return json_decode(json_encode(['error' => $error]));
        }

        // No need to call curl_close() in PHP 8.0+; handle is auto-closed by garbage collector
        return json_decode($res);
    }

    /**
     * Send message to chat.
     *
     * @param int|string $chatId Chat ID
     * @param string $text Message text
     * @param array<string, mixed> $options Additional options (parse_mode, reply_to_message_id, etc.)
     * @return object|null API response
     */
    public function sendMessage(int|string $chatId, string $text, array $options = []): ?object
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
        ], $options);

        return $this->call('sendMessage', $data);
    }

    /**
     * Send photo to chat.
     *
     * @param int|string $chatId Chat ID
     * @param string|\CURLFile $photo Photo file path or CURLFile
     * @param array<string, mixed> $options Additional options (caption, parse_mode, etc.)
     * @return object|null API response
     */
    public function sendPhoto(int|string $chatId, string|\CURLFile $photo, array $options = []): ?object
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'photo' => $photo,
        ], $options);

        return $this->call('sendPhoto', $data);
    }

    /**
     * Send document to chat.
     *
     * @param int|string $chatId Chat ID
     * @param string|\CURLFile $document Document file path or CURLFile
     * @param array<string, mixed> $options Additional options (caption, parse_mode, etc.)
     * @return object|null API response
     */
    public function sendDocument(int|string $chatId, string|\CURLFile $document, array $options = []): ?object
    {
        $data = array_merge([
            'chat_id' => $chatId,
            'document' => $document,
        ], $options);

        return $this->call('sendDocument', $data);
    }

    /**
     * Delete message.
     *
     * @param int|string $chatId Chat ID
     * @param int $messageId Message ID to delete
     * @return object|null API response
     */
    public function deleteMessage(int|string $chatId, int $messageId): ?object
    {
        return $this->call('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ]);
    }
}

