<?php

namespace App\Component\Notice\Feishu\MsgTemplate;

use App\Component\Notice\Feishu\Enum\MsgTypeEnum;

class Text extends MsgTemplate
{

    protected function msgType(): MsgTypeEnum
    {
        return MsgTypeEnum::MSG_TYPE_TEXT();
    }

    protected function getData(): array
    {
        return ['text' => is_null($this->project) ? $this->data : sprintf("%s: %s", $this->project, $this->data)];
    }

    public function setText(string $text): Text
    {
        $this->data = $text;
        return $this;
    }

    public function toStringMsg(): string
    {
        return $this->data;
    }
}