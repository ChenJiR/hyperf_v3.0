<?php

namespace App\Component\Notice\DingDing\MsgTemplate;

use App\Component\Notice\DingDing\Enum\Bot;
use App\Component\Notice\DingDing\SendMsg;
use JsonSerializable;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use App\Component\Notice\DingDing\Enum\MsgTypeEnum;

abstract class MsgTemplate implements JsonSerializable, Arrayable, Jsonable
{

    /**
     * @var array|string
     */
    protected $data;

    /**
     * @var MsgTypeEnum
     */
    protected $type;

    /**
     * 项目
     * @var string|null
     */
    protected $project;

    protected array $atMobiles = [];

    protected array $atUserIds = [];

    protected bool $isAtAll = false;

    abstract protected function msgType(): MsgTypeEnum;

    abstract protected function getData(): array;

    public function jsonSerialize(): string
    {
        return $this->toJson();
    }

    public function toArray(): array
    {
        $type = $this->msgType();
        return [
            "msgtype" => $type->getMsgType(),
            $type->getContentKey() => $this->getData(),
            'at' => [
                'atMobiles' => $this->atMobiles,
                'atUserIds' => $this->atUserIds,
                'isAtAll' => $this->isAtAll
            ]
        ];
    }

    public function toJson(int $options = JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->data, $options);
    }

    public function __toString(): string
    {
        return $this->toJson();
    }

    public function setProject(?string $project_name = null)
    {
        $this->project = $project_name;
        return $this;
    }

    public function setAt(array $atMobiles = [], array $atUserIds = [], bool $isAtAll = false)
    {
        $this->atMobiles = $atMobiles;
        $this->atUserIds = $atUserIds;
        $this->isAtAll = $isAtAll;
        return $this;
    }

    public function send(?Bot $bot = null)
    {
        return SendMsg::send($this, $bot ?? Bot::getBot($this->project));
    }

    public static function arrayToString(array $array, bool $need_type = true, int $indent = 0): string
    {
        $indent_space = '';
        $indent_num = $indent;
        while ($indent_num--) {
            $indent_space .= " ";
        }
        $indent_string = $indent_space . '- ';
        $content = '';
        $is_index_ary = (array_keys($array) === range(0, count($array) - 1));
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $content .= sprintf(
                    "$indent_string%s%s%s(array)" . PHP_EOL . $indent_string . '[' . PHP_EOL . '%s' . $indent_string . ']' . PHP_EOL,
                    $is_index_ary ? '' : $key,
                    $is_index_ary ? '' : ($need_type ? '(' . gettype($key) . ')' : ''),
                    $is_index_ary ? '' : ' :',
                    self::arrayToString($value, $need_type, $indent + 2)
                );
            } else {
                $content .= sprintf(
                    "$indent_string%s%s%s%s%s" . PHP_EOL,
                    $is_index_ary ? '' : $key,
                    $is_index_ary ? '' : ($need_type ? '(' . gettype($key) . ')' : ''),
                    $is_index_ary ? '' : ' :',
                    $value, $need_type ? ' (' . gettype($value) . ')' : ''
                );
            }
        }
        return $content;
    }

}