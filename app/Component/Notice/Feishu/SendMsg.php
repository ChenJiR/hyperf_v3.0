<?php

namespace App\Component\Notice\Feishu;

use App\Component\Cache\RedisCache;
use App\Component\HttpClient\HttpClientComponent;
use App\Component\Notice\Feishu\Enum\Bot;
use App\Component\Notice\Feishu\MsgTemplate\MsgTemplate;
use App\Component\Notice\Feishu\MsgTemplate\RemindCard\Error;
use App\Util\TimeHelper;
use GuzzleHttp\Exception\GuzzleException;
use Hyperf\Coroutine\Coroutine;
use function Hyperf\Support\make;

class SendMsg
{

    /**
     * 短时间发送大量错误消息，会自动熔断
     */
    public static function send(MsgTemplate $msgTemplate, ?Bot $bot = null)
    {
        $redis = make(RedisCache::class);
        while (!$redis->lock('feishusms:datalock')) {
            Coroutine::sleep(0.15);
        }
        $feishusms_data = $redis->get('feishusms:data');
        empty($feishusms_data)
            ? $feishusms_data = ['error' => [], 'start_time' => TimeHelper::nowTime(), 'times' => 1]
            : $feishusms_data['times']++;

        if ($feishusms_data['times'] > 3) {
            // 若短时间内 错误超过3次，则进入熔断机制
            // 收集熔断期间未告警的错误
            $feishusms_data['error'][] = $msgTemplate->toStringMsg();

            if (
                count($feishusms_data['error']) > 3 &&
                $redis->lock('feishusms:fusinglock', 4)
            ) {
                $feishusms = (new Error())
                    ->setTitle('短时间内收集到大量消息')
                    ->setMsg("自{$feishusms_data['start_time']} 开始，已监控到{$feishusms_data['times']}次错误")
                    ->addNotNullFields('error', $feishusms_data['error']);
                self::request(($bot ?? Bot::PHP_BOT())->getBotHookUrl(), $feishusms->toArray());

                //发布结束后，将集合清空，以免重复告警
                $feishusms_data['error'] = [];
            }
            $redis->set('feishusms:data', $feishusms_data, 10);
            $redis->unlock('feishusms:datalock');
            return true;
        } else {
            $redis->set('feishusms:data', $feishusms_data, 10);
            $redis->unlock('feishusms:datalock');
            return self::request(($bot ?? Bot::PHP_BOT())->getBotHookUrl(), $msgTemplate->toArray());
        }

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