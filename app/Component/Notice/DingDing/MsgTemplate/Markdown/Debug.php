<?php

namespace App\Component\Notice\DingDing\MsgTemplate\Markdown;

class Debug extends RemindCard
{

    public function setTitle(string $title): RemindCard
    {
        $this->title = "DEBUG: " . $title;
        return $this;
    }
}