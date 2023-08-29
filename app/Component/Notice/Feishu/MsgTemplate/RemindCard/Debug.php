<?php

namespace App\Component\Notice\Feishu\MsgTemplate\RemindCard;

class Debug extends RemindCard
{

    function titleColor(): string
    {
        return "blue";
    }

    public function setTitle(string $title): RemindCard
    {
        $this->title = "DEBUG: " . $title;
        return $this;
    }
}