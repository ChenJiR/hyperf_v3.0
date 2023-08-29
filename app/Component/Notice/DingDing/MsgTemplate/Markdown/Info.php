<?php

namespace App\Component\Notice\DingDing\MsgTemplate\Markdown;

class Info extends RemindCard
{

    public function setTitle(string $title): RemindCard
    {
        $this->title = "INFO: " . $title;
        return $this;
    }
}