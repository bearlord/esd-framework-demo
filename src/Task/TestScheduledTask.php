<?php


namespace App\Task;

use ESD\Core\Plugins\Logger\GetLogger;
use ESD\Plugins\AnnotationsScan\Annotation\Component;
use ESD\Plugins\Scheduled\Annotation\Scheduled;
use ESD\Plugins\Scheduled\Beans\ScheduledTask;
use ESD\Plugins\Scheduled\ScheduledConfig;

/**
 * @Component()
 * Class TestScheduledTask
 * @package App\Task\TestScheduledTask
 */
class TestScheduledTask
{
    /**
     * Scheduled(cron="@secondly")
     */
    public function test()
    {
        $message = sprintf("%s - %s", date("Y-m-d H:i:s"), "这是一次定时调用");
        var_dump($message);

        //添加动态任务
//        $this->dynamicAdd();
    }

    public function say()
    {
        printf("%s Hello \n", date("Y-m-d H:i:s"));
    }

    public function dynamicAdd()
    {
        $scheduledTask = new ScheduledTask(
            'say',
            '@minutely',
            __CLASS__,
            'say',
            'ScheduledGroup');

        $scheduledConfig = new ScheduledConfig();
        $scheduledConfig->addScheduled($scheduledTask);
    }
}