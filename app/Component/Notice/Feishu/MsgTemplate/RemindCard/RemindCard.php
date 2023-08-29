<?php

namespace App\Component\Notice\Feishu\MsgTemplate\RemindCard;

use App\Component\Notice\Feishu\Enum\MsgTypeEnum;
use App\Component\Notice\Feishu\MsgTemplate\MsgTemplate;

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

    abstract function titleColor(): string;

    public function config(array $config = []): array
    {
        return array_merge(["wide_screen_mode" => true, "update_multi" => true], $config);
    }

    protected function msgType(): MsgTypeEnum
    {
        return MsgTypeEnum::MSG_TYPE_CARD();
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
        $elements = [];
        if (!empty($this->fields)) {
            $fields = [];
            foreach ($this->fields as $field) {
                $fields[] = [
                    'is_short' => false,
                    'text' => [
                        'content' => "**{$field['title']}**\n{$field['content']}",
                        'tag' => 'lark_md'
                    ]
                ];
            }
            $elements[] = ["tag" => "div", "fields" => $fields];
        }
        if (!empty($this->msg)) {
            $elements[] = ["tag" => "div", "text" => ["content" => $this->msg, "tag" => "lark_md"]];
        }
        if (!empty($this->note)) {
            $elements[] = ["tag" => "hr"];
            $elements[] = ["tag" => "note", "text" => ["content" => $this->msg, "tag" => "lark_md"]];
        }
        $this->data = [
            'config' => $this->config(),
            'header' => [
                'template' => $this->titleColor(),
                'title' => [
                    'content' => $this->title,
                    'tag' => 'plain_text'
                ]
            ],
            "elements" => $elements
        ];
        return $this;
    }

}