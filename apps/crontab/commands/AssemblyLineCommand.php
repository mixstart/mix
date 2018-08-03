<?php

namespace apps\crontab\commands;

use mix\console\ExitCode;
use mix\facades\Input;
use mix\task\CenterWorker;
use mix\task\LeftWorker;
use mix\task\ProcessPoolTaskExecutor;
use mix\task\RightWorker;
use mix\task\TaskExecutor;

/**
 * 流水线模式范例
 * @author 刘健 <coder.liu@qq.com>
 */
class AssemblyLineCommand extends BaseCommand
{

    // 初始化事件
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 获取程序名称
        $this->programName = Input::getCommandName();
    }

    /**
     * 获取服务
     * @return ProcessPoolTaskExecutor
     */
    public function getTaskService()
    {
        return create_object(
            [
                // 类路径
                'class'         => 'mix\task\ProcessPoolTaskExecutor',
                // 服务名称
                'name'          => "mix-daemon: {$this->programName}",
                // 执行模式
                'mode'          => TaskExecutor::MODE_ASSEMBLY_LINE,
                // 左进程数
                'leftProcess'   => 1,
                // 中进程数
                'centerProcess' => 5,
                // 右进程数
                'rightProcess'  => 1,
                // 最大执行次数
                'maxExecutions' => 16000,
                // 队列名称
                'queueName'     => __FILE__,
            ]
        );
    }

    // 执行任务
    public function actionExec()
    {
        // 预处理
        parent::actionExec();
        // 启动服务
        $service = $this->getTaskService();
        $service->on('LeftStart', [$this, 'onLeftStart']);
        $service->on('CenterMessage', [$this, 'onCenterMessage']);
        $service->on('RightMessage', [$this, 'onRightMessage']);
        $service->start();
        // 返回退出码
        return ExitCode::OK;
    }

    // 左进程启动事件回调函数
    public function onLeftStart(LeftWorker $worker)
    {
        // 模型内使用长连接版本的数据库组件，这样组件会自动帮你维护连接不断线
        $tableModel = new \apps\common\models\TableModel();
        // 取出全量数据一行一行推送给中进程去处理
        foreach ($tableModel->getAll() as $item) {
            // 将消息发送给中进程去处理，消息有长度限制 (https://wiki.swoole.com/wiki/page/290.html)
            $worker->send($item);
        }
    }

    // 中进程消息事件回调函数
    public function onCenterMessage(CenterWorker $worker, $data)
    {
        // 对消息进行处理，比如：IP转换，经纬度转换等
        // ...
        // 将处理完成的消息发送给右进程去处理，消息有长度限制 (https://wiki.swoole.com/wiki/page/290.html)
        $worker->send($data);
    }

    // 右进程启动事件回调函数
    public function onRightMessage(RightWorker $worker, $data)
    {
        // 模型内使用长连接版本的数据库组件，这样组件会自动帮你维护连接不断线
        $tableModel = new \apps\common\models\TableModel();
        // 将处理完成的消息存入数据库
        $tableModel->insert($data);
    }

}
