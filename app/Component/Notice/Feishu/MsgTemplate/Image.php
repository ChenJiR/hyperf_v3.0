<?php

namespace App\Component\Notice\Feishu\MsgTemplate;

use App\Component\Notice\Feishu\Enum\MsgTypeEnum;

class Image extends MsgTemplate
{

    protected function msgType(): MsgTypeEnum
    {
        return MsgTypeEnum::MSG_TYPE_IMAGE();
    }

    protected function getData(): array
    {
        return $this->data;
    }

    public function setImage(string $image_key): Image
    {
        $this->data = $image_key;
        return $this;
    }

    public function toStringMsg(): string
    {
        return '';
    }
}