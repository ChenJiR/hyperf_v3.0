<?php

namespace App\Component\Sms\Impl;

use App\Component\Sms\Exception\SmsException;
use App\Component\Sms\SmsInterface;
use App\Component\Sms\SMSTemplateEnum;
use App\Logger\Log;
use function Hyperf\Support\env;

class HuaweiSms implements SmsInterface
{
    public function send(array $phoneAry, array $params, SMSTemplateEnum $template): bool
    {
        if (empty($phoneAry)) throw new SmsException('发送手机号不能为空');
        if (count($params) != $template->getParamsLength()) throw new SmsException('发送短信参数错误');

        $phoneAry = array_map(function ($item) {
            return '+86' . ltrim($item, '+86');
        }, $phoneAry);

        //请求Headers
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: WSSE realm="SDP",profile="UsernameToken",type="Appkey"',
            'X-WSSE: ' . $this->buildWsseHeader()
        ];
        //请求Body
        $data = http_build_query([
            'from' => $template->getSender(),
            'to' => implode(',', $phoneAry),
            'templateId' => $template->getTemplateId(),
            'templateParas' => json_encode($params),
            'statusCallback' => '',
            //使用国内短信通用模板时,必须填写签名名称
            'signature' => env('HUAWEISMS_SIGNATURE', '顶端新闻'),
        ]);

        $context_options = [
            'http' => ['method' => 'POST', 'header' => $headers, 'content' => $data, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false] //为防止因HTTPS证书认证失败造成API调用失败，需要先忽略证书信任问题
        ];

        $response = file_get_contents(
            env('HUAWEISMS_SEND_API', 'https://smsapi.cn-north-4.myhuaweicloud.com:443/sms/batchSendSms/v1'),
            false,
            stream_context_create($context_options)
        );
        $response = json_decode($response, true);

        if ($response['code'] == '000000') {
            return true;
        } else {
            Log::error('SmsSend_Error', ['request' => $data, 'response' => $response], 'SmsSend_Error');
            throw new SmsException('短信发送错误 errcode:' . $response['code'] . json_encode($response));
        }
    }

    /**
     * 构造X-WSSE参数值
     * @return string
     */
    private function buildWsseHeader(): string
    {
        date_default_timezone_set('Asia/Shanghai');
        $appKey = env('HUAWEISMS_APPKEY', '38qL28Bikoq8ab22KzZLL8G7FS3i');
        $appSecret = env('HUAWEISMS_APPSECRET', 'ytKpcyIAQkykRyQfiKeQD4w3ZNUB');
        $now = date('Y-m-d\TH:i:s\Z'); //Created
        $nonce = uniqid(); //Nonce
        $base64 = base64_encode(hash('sha256', ($nonce . $now . $appSecret))); //PasswordDigest
        return sprintf(
            "UsernameToken Username=\"%s\",PasswordDigest=\"%s\",Nonce=\"%s\",Created=\"%s\"",
            $appKey, $base64, $nonce, $now
        );
    }
}