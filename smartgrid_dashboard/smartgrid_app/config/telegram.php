<?php
declare(strict_types=1);

const TELEGRAM_BOT_TOKEN = '8745288293:AAEXEWNwpfx2V6csoPpPzU9NgAgI8qb2Thg';
const TELEGRAM_CHAT_ID = '5249947478';

function telegram_is_configured(): bool
{
    return TELEGRAM_BOT_TOKEN !== 'A_REMPLACER_PAR_LE_TOKEN_DU_BOT'
        && TELEGRAM_CHAT_ID !== 'A_REMPLACER_PAR_LE_CHAT_ID'
        && TELEGRAM_BOT_TOKEN !== ''
        && TELEGRAM_CHAT_ID !== '';
}

function send_telegram_message(string $message, ?string $chatId = null): bool
{
    if (!telegram_is_configured()) {
        return false;
    }

    $targetChatId = $chatId ?: TELEGRAM_CHAT_ID;
    if ($targetChatId === '') {
        return false;
    }

    $url = 'https://api.telegram.org/bot' . TELEGRAM_BOT_TOKEN . '/sendMessage';
    $payload = http_build_query([
        'chat_id' => $targetChatId,
        'text' => $message,
        'parse_mode' => 'HTML',
    ]);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 8,
        ],
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return false;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) && ($decoded['ok'] ?? false) === true;
}
