<?php

namespace App\Component\Notice\Feishu\MsgTemplate\RemindCard;

class Warn extends RemindCard
{

    function titleColor(): string
    {
        return "yellow";
    }

    public function setTitle(string $title): RemindCard
    {
        $this->title = "WARN: " . $title;
        return $this;
    }
}