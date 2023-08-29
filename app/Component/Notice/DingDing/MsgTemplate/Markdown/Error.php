<?php

namespace App\Component\Notice\DingDing\MsgTemplate\Markdown;

class Error extends RemindCard
{

    public function setTitle(string $title): RemindCard
    {
        $this->title = "ERROR: " . $title;
        return $this;
    }
}