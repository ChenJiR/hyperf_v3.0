<?php

namespace App\Component\Notice\DingDing\MsgTemplate\Markdown;

use App\Component\Notice\DingDing\Enum\MsgTypeEnum;
use App\Component\Notice\DingDing\MsgTemplate\MsgTemplate;

abstract class RemindCard extends MsgTemplate
{

    /**
     * 时间
     * @var string
     */
    protected $remind_time;

    /**
     * @var array
     */
    protected $fields = [];

    /**
     * @var string
     */
    protected $msg = "";

    /**
     * @var string
     */
    protected $node = "";

    /**
     * @var string
     */
    protected $title;

    protected function msgType(): MsgTypeEnum
    {
        return MsgTypeEnum::MSG_TYPE_MARKDOWN();
    }

    protected function getData(): array
    {
        $this->generateContent();
        return $this->data;
    }

    public function toStringMsg(): string
    {
        return "$this->project: $this->title($this->msg)";
    }

    public function remindTime($remind_time = null): RemindCard
    {
        $remind_time = $remind_time ?? time();
        $this->remind_time = is_numeric($remind_time) ? date('Y-m-d H:i:s', $remind_time) : $remind_time;
        return $this->addFields("🕐 时间：", $this->remind_time);
    }

    public function setProject(?string $project_name = null)
    {
        $this->project = $project_name;
        return $this->addFields("📋 项目：", $this->project);
    }

    public function addFields(string $title, $content): RemindCard
    {
        $this->fields[] = ['title' => $title, 'content' => is_array($content) ? self::arrayToString($content, false) : '  ' . $content];
        return $this;
    }

    public function addNotNullFields(string $title, $content): RemindCard
    {
        return !empty($content) ? $this->addFields($title, $content) : $this;
    }

    public function setTitle(string $title): RemindCard
    {
        $this->title = $title;
        return $this;
    }

    public function setMsg(string $msg): RemindCard
    {
        $this->msg = $msg;
        return $this;
    }

    public function setNode(string $note): RemindCard
    {
        $this->node = $note;
        return $this;
    }

    /**
     * @return self
     */
    protected function generateContent(): RemindCard
    {
        $content = "## $this->title ##\n ------------ \n";
        if (!empty($this->fields)) {
            foreach ($this->fields as $field) {
                $content .= " ### {$field['title']} ### \n{$field['content']}\n";
            }
        }
        if (!empty($this->msg)) {
            $content .= "\n ------------ \n$this->msg\n";
        }
        if (!empty($this->note)) {
            $content .= " * $this->msg * \n";
        }
        $this->data = ['title' => $this->title, 'text' => $content];
        return $this;
    }

}