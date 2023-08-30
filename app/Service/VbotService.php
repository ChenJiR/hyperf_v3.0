<?php

declare(strict_types=1);

namespace App\Service;

use App\Component\Cache\RedisCache;
use App\Logger\Log;
use Hanson\Vbot\Message\Message;
use Hanson\Vbot\Message\Text;
use Hyperf\Codec\Json;
use Hyperf\Collection\Collection;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Throwable;

class VbotService
{

    #[Inject]
    protected AiChatSwooleService $aiChatSwooleService;

    #[Inject]
    protected RedisCache $redisCache;

    public function handle(Collection $message): void
    {
        di()->get(LoggerFactory::class)->get('vbot.message')->info(Json::encode($message->toArray()));

        try {
            match ($message->get('type')) {
                Text::TYPE => $this->handleText($message),
                default => null
            };
        } catch (Throwable $exception) {
            Log::error((string)$exception);
        }
    }

    /**
     * 接受到消息.
     */
    public function handleText(Collection $message): void
    {
        $content = trim($message['pure']);

        $isAt = $message['isAt'];
        $fromType = $message['fromType'];

        switch ($fromType) {
            case Message::FROM_TYPE_GROUP:
                // 群聊消息 若不是@自己 不处理
                if (!$isAt) return;
                $sender = $message['sender']['NickName'];
                break;
            case Message::FROM_TYPE_FRIEND:
                $sender = $message['from']['NickName'];
                break;
            default:
                return;
        }

        try {
            $result = $this->aiChatSwooleService->completions($message['raw']['FromUserName'], $message['raw']['ToUserName'], $content);
        } catch (Throwable $exception) {
            Log::error((string)$exception);
            $result = '我不知道，别问我';
        }

        $reply = sprintf('「%s：%s」', $sender, $content) . PHP_EOL
            . '- - - - - - - - - - - - - - -' . PHP_EOL
            . $result;

        Text::send($message['from']['UserName'], $reply);
    }
}