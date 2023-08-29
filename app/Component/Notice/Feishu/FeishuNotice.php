<?php

namespace App\Component\Notice\Feishu;

use App\Component\Notice\Feishu\MsgTemplate\RemindCard\Debug;
use App\Component\Notice\Feishu\MsgTemplate\RemindCard\Error;
use App\Component\Notice\Feishu\MsgTemplate\RemindCard\Info;
use App\Component\Notice\Feishu\MsgTemplate\RemindCard\Warn;
use App\Component\Notice\Feishu\MsgTemplate\Text;
use App\Component\Notice\NoticeInterface;
use Hyperf\Command\Command;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Context\ApplicationContext;
use Symfony\Component\Console\Input\InputInterface;
use Throwable;

class FeishuNotice implements NoticeInterface
{

    /**
     * 发送文本消息
     * @param string $text
     * @param null $remind_time
     * @param string $project
     */
    public function textMsg(string $text, $remind_time = null, string $project = self::PROJECT)
    {
        if (!$text) return;

        (new Text())
            ->setProject($project)
            ->setText($text)
            ->send();
    }

    /**
     * 发送卡片消息
     * @param string $level CARD_MSG_DEBUG | CARD_MSG_INFO | CARD_MSG_WARN | CARD_MSG_WARN
     * @param string $title 卡片标题
     * @param string $msg 卡片消息
     * @param array $data 附带信息
     * @param null $remind_time
     */
    public function cardMsg(string $level, string $title, string $msg, array $data = [], $remind_time = null, string $project = self::PROJECT)
    {
        if (!$title || !$msg) return;

        $remind_time = $remind_time ?? time();
        $remind_time = is_numeric($remind_time) ? date('Y-m-d H:i:s', $remind_time) : $remind_time;
        $fsmsg = match ($level) {
            NoticeInterface::CARD_MSG_DEBUG => new Debug(),
            default => new Info(),
            NoticeInterface::CARD_MSG_WARN => new Warn(),
            NoticeInterface::CARD_MSG_ERROR => new Error(),
        };
        $fsmsg->setProject($project)
            ->remindTime($remind_time ?? null)
            ->setTitle($title)
            ->setMsg($msg);
        foreach ($data as $field => $value) {
            $fsmsg->addNotNullFields($field, $value);
        }
        $fsmsg->send();
    }

    /**
     * 发送http错误消息
     * @param Throwable $e
     * @param RequestInterface|null $request
     * @param null $remind_time
     */
    public function httpError(Throwable $e, ?RequestInterface $request = null, $remind_time = null, string $project = self::PROJECT)
    {
        $remind_time = $remind_time ?? time();
        $remind_time = is_numeric($remind_time) ? date('Y-m-d H:i:s', $remind_time) : $remind_time;
        $request = $request ?? ApplicationContext::getContainer()->get(RequestInterface::class);

        $header = [];
        foreach ($request->getHeaders() as $name => $values) {
            $header[$name] = implode(", ", $values);
        }
        (new Error())
            ->setProject($project)
            ->remindTime($remind_time)
            ->setTitle("HTTP {$request->getMethod()}: " . $request->getUri()->getPath())
            ->addFields("Params:", $request->all())
            ->addFields("Headers:", $header)
            ->addFields("Exception File:", "{$e->getFile()} ( line:{$e->getLine()} )")
            ->setMsg(sprintf('%s : %s', get_class($e), $e->getMessage()))
            ->send();
    }

    /**
     * 发送cli错误消息
     * @param Throwable $e
     * @param Command $command
     * @param InputInterface $input
     * @param null $remind_time
     * @param string $project
     */
    public function commandError(Throwable $e, Command $command, InputInterface $input, $remind_time = null, string $project = self::PROJECT)
    {
        $remind_time = $remind_time ?? time();
        $remind_time = is_numeric($remind_time) ? date('Y-m-d H:i:s', $remind_time) : $remind_time;
        (new Error())
            ->remindTime($remind_time)
            ->setTitle("COMMAND {$command->getName()}")
            ->setProject($project)
            ->addNotNullFields("Description", $command->getDescription())
            ->addNotNullFields("Input options:", $input->getOptions())
            ->addNotNullFields("Input arguments:", $input->getArguments())
            ->addFields("Exception File:", "{$e->getFile()} ( line:{$e->getLine()} )")
            ->setMsg(sprintf('%s : %s', get_class($e), $e->getMessage()))
            ->send();
    }

}