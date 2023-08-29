<?php

namespace App\Component\Notice\DingDing\MsgTemplate\Markdown;

class Warn extends RemindCard
{

    public function setTitle(string $title): RemindCard
    {
        $this->title = "WARN: " . $title;
        return $this;
    }
}