<?php

namespace App\Component\Sms;


interface SmsInterface
{
    public function send(array $phoneAry, array $params, SMSTemplateEnum $template): bool;
}