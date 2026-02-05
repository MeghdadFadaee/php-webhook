<?php

class MainController
{
    /**
     * Handle incoming webhook request.
     */
    public function __invoke(Request $request): void
    {
        if (!$request->has('token')) {
            return;
        }

        $data = $request->validate([
            'token' => 'required',
        ]);

        $token = $data['token'];
        $update = $request->json();

        $baseurl = Messenger::tryFromIp($request->ip())?->getApiBaseurl();
        $url = "$baseurl/bot{$token}/sendMessage";

        // Preparing payload
        $text = $this->formatBotUpdate($update);
        $chat_id = Arr::get($update, 'message.chat.id');
        $payload = compact('chat_id', 'text');

        $payload['result'] = Client::make()->json($payload)->post($url)->body();
        $payload['request_id'] = $request->id();

        Logger::write('bot-call', $payload);
        JsonResponse::successful('Controlled!', $payload)->exit();
    }

    /**
     * Convert bot update payload into a clean, human-readable text.
     */
    public function formatBotUpdate(array $update): string
    {
        $message = $update['message'] ?? [];
        $from = $message['from'] ?? [];
        $chat = $message['chat'] ?? [];

        // Update info
        $updateId = $update['update_id'] ?? '—';

        // User info
        $userId = $from['id'] ?? '—';
        $isBot = isset($from['is_bot']) ? ($from['is_bot'] ? 'Yes' : 'No') : '—';
        $first = $from['first_name'] ?? '';
        $last = $from['last_name'] ?? '';
        $username = $from['username'] ?? null;
        $username = $username ? '@'.$username : '—';
        $fullName = trim($first.' '.$last);

        // Chat info
        $chatId = $chat['id'] ?? '—';
        $chatType = $chat['type'] ?? '—';
        $chatUsername = $chat['username'] ?? null;
        $chatUsername = $chatUsername ? '@'.$chatUsername : '—';
        $chatFirst = $chat['first_name'] ?? '';
        $chatLast = $chat['last_name'] ?? '';
        $chatName = trim($chatFirst.' '.$chatLast);

        // Message info
        $messageId = $message['message_id'] ?? '—';
        $text = $message['text'] ?? '—';
        $date = isset($message['date'])
            ? gmdate('Y-m-d H:i:s', $message['date'])
            : '—';

        return <<<TEXT
📦 Bot Update
━━━━━━━━━━━━━━━━━━
🆔 Update ID: {$updateId}

👤 User Information
• Name: {$fullName}
• Username: {$username}
• User ID: {$userId}
• Is Bot: {$isBot}

💬 Chat Information
• Chat ID: {$chatId}
• Chat Type: {$chatType}
• Chat Username: {$chatUsername}
• Chat Name: {$chatName}

✉️ Message Information
• Message ID: {$messageId}
• Message Date (UTC): {$date}
• Message Text:
{$text}
TEXT;
    }
}