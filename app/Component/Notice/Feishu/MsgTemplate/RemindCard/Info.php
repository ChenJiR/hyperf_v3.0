<?php

namespace App\Component\Notice\Feishu\MsgTemplate\RemindCard;

class Info extends RemindCard
{

    function titleColor(): string
    {
        return "green";
    }

    public function setTitle(string $title): RemindCard
    {
        $this->title = "INFO: " . $title;
        return $this;
    }
}