<?php

namespace App\Component\Notice\Feishu\MsgTemplate\RemindCard;

class Error extends RemindCard
{

    function titleColor(): string
    {
        return "red";
    }

    public function setTitle(string $title): RemindCard
    {
        $this->title = "ERROR: " . $title;
        return $this;
    }
}