<?php

namespace App\Service;

use App\Component\Cache\RedisCache;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Hyperf\Codec\Json;
use Hyperf\Config\Annotation\Value;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Guzzle\ClientFactory;

class AiChatSwooleService
{

    #[Inject]
    protected ClientFactory $clientFactory;

    #[Inject]
    protected RedisCache $redisCache;

    const SESSIONKEY_PREFIX = 'session:';

    const KEY = '39459-99F1A76FD61A7ED474DC5ADB0C4BCADF94505A63';

    private function client(): Client
    {
        return $this->clientFactory->create();
    }

    private function header(): array
    {
        return [
            'Authorization' => 'Bearer ' . self::KEY,
            'Content-Type' => 'application/json',
        ];
    }

    public function completions(string $from_user_id, string $to_user_id, string $content): string
    {
        $session_key = self::SESSIONKEY_PREFIX . md5($from_user_id . $to_user_id);
        $messages = $this->redisCache->get($session_key, []);
        $messages[] = ['role' => 'user', 'content' => $content];

        $res = $this->client()->requestAsync(
            'POST', 'https://chat.swoole.com/v1/chat/completions',
            [
                'headers' => $this->header(),
                'body' => Json::encode(
                    [
                        'model' => 'llama2-6b',
                        'messages' => $messages,
                    ]
                )
            ]
        )->wait();

        $result = Json::decode((string)$res->getBody());
        $messages = array_column($result['data']['choices'], 'message');
        $this->redisCache->set($session_key, $messages, 60 * 20);

        return end($messages)['content'] ?? '我不知道，别问我';
    }
}