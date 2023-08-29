<?php

namespace App\Component\Notice\DingDing;

use App\Component\Cache\RedisCache;
use App\Component\HttpClient\HttpClientComponent;
use App\Component\Notice\DingDing\Enum\Bot;
use App\Component\Notice\DingDing\MsgTemplate\Markdown\Error;
use App\Component\Notice\DingDing\MsgTemplate\MsgTemplate;
use App\Util\TimeHelper;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Context\ApplicationContext;
use Hyperf\Coroutine\Coroutine;

class SendMsg
{

    /**
     * 短时间发送大量错误消息，会自动熔断
     */
    public static function send(MsgTemplate $msgTemplate, ?Bot $bot = null)
    {
        $redis = ApplicationContext::getContainer()->get(RedisCache::class);
        while (!$redis->lock('ddsms:datalock')) {
            Coroutine::sleep(0.15);
        }

        $bot = $bot ?? Bot::PHP_BOT();
        $time = time() . '000';
        $api = $bot->getBotHookUrl() . "&timestamp=$time&sign=" . self::buildSign($bot, $time);

        $ddsms_data = $redis->get('ddsms:data');
        empty($ddsms_data)
            ? $ddsms_data = ['error' => [], 'start_time' => TimeHelper::nowTime(), 'times' => 1]
            : $ddsms_data['times']++;

        if ($ddsms_data['times'] > 3) {
            // 若短时间内 错误超过3次，则进入熔断机制
            // 收集熔断期间未告警的错误
            $ddsms_data['error'][] = $msgTemplate->toStringMsg();

            if (
                count($ddsms_data['error']) > 3 &&
                $redis->lock('ddsms:fusinglock', 4)
            ) {
                $ddsms = (new Error())
                    ->setTitle('短时间内收集到大量消息')
                    ->setMsg("自{$ddsms_data['start_time']} 开始，已监控到{$ddsms_data['times']}次错误")
                    ->addNotNullFields('error', $ddsms_data['error']);
                self::request($api, $ddsms->toArray());

                //发布结束后，将集合清空，以免重复告警
                $ddsms_data['error'] = [];
            }
            $redis->set('ddsms:data', $ddsms_data, 10);
            $redis->unlock('ddsms:datalock');
            return true;
        } else {
            $redis->set('ddsms:data', $ddsms_data, 10);
            $redis->unlock('ddsms:datalock');
            return self::request($api, $msgTemplate->toArray());
        }

    }

    private static function buildSign(Bot $bot, int|string $timestamp): ?string
    {
        $secret = $bot->getBotSecret();
        if (empty($secret)) return null;

        $sign = sprintf("%s\n%s", $timestamp, $secret);
        return urlencode(base64_encode(hash_hmac('sha256', $sign, $secret, true)));
    }

    /**
     * 发起请求
     * @param string $api
     * @param array $params
     * @return bool|string
     * @throws GuzzleException
     */
    private static function request(string $api, array $params)
    {
        return (new HttpClientComponent($api, $params))
            ->setApiMethod(HttpClientComponent::API_METHOD_JSON_POST)
            ->getResult();
    }

}