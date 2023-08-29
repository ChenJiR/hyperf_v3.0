<?php

namespace App\Component\Notice\Feishu\MsgTemplate;

use App\Component\Notice\Feishu\Enum\Bot;
use App\Component\Notice\Feishu\SendMsg;
use JsonSerializable;
use Hyperf\Contract\Arrayable;
use Hyperf\Contract\Jsonable;
use App\Component\Notice\Feishu\Enum\MsgTypeEnum;

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

    abstract protected function msgType(): MsgTypeEnum;

    abstract protected function getData(): array;

    public function jsonSerialize(): string
    {
        return $this->toJson();
    }

    public function toArray(): array
    {
        $type = $this->msgType();
        return ["msg_type" => $type->getMsgType(), $type->getContentKey() => $this->getData()];
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

    public function send(?Bot $bot = null)
    {
        return SendMsg::send($this, $bot ?? Bot::getBot($this->project));
    }

    public static function arrayToString(array $array, bool $need_type = true, int $indent = 4): string
    {
        $indent_string = '';
        $indent_num = $indent;
        while ($indent_num--) {
            $indent_string .= " ";
        }
        $content = '';
        $is_index_ary = (array_keys($array) === range(0, count($array) - 1));
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $content .= sprintf(
                    "$indent_string%s%s%s(array)" . PHP_EOL . $indent_string . '[' . PHP_EOL . '%s' . $indent_string . ']' . PHP_EOL,
                    $is_index_ary ? '' : $key,
                    $is_index_ary ? '' : ($need_type ? '(' . gettype($key) . ')' : ''),
                    $is_index_ary ? '' : ' :',
                    self::arrayToString($value, $need_type, $indent + 4)
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