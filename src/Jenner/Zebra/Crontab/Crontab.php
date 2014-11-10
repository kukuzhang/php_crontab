<?php
/**
 * Created by PhpStorm.
 * User: Jenner
 * Date: 14-11-7
 * Time: 下午5:09
 */

namespace Jenner\Zebra\Crontab;

use \Jenner\Zebra\MultiProcess\Process;
use \Jenner\Zebra\MultiProcess\ProcessManager;


/**
 * Class Crontab
 * @package Jenner\Zebra\Crontab
 */
class Crontab
{
    /**
     * @var 定时任务配置
     * 格式：[['name'=>'服务监控', 'cmd'=>'要执行的命令', 'output_file'=>'输出重定向', 'time_rule'=>'时间规则(crontab规则)']]
     */
    protected $config;

    /**
     * @var null
     * 日志文件[推荐使用绝对路径，请确保有可写权限，否则日志将不会被记录]
     */
    protected $log_file;

    /**
     * @param $crontab_config
     * @param $log_file
     */
    public function __construct($crontab_config, $log_file = null)
    {
        $this->config = $crontab_config;
        if (is_null($log_file)) {
            $this->log_file = '/var/log/php_crontab.log';
        } else {
            $this->log_file = $log_file;
        }
    }

    /**
     * 创建子进程执行定时任务
     */
    public function start()
    {
        $manager = new ProcessManager();
        $missions = $this->getMission();
        foreach ($missions as $mission) {
            $mission_executor = new Mission($mission['cmd'], $mission['output_file']);
            $this->log($mission['cmd']);
            $manager->fork(new Process([$mission_executor, 'start'], $mission['name']));
        }
        do {
            foreach ($manager->getChildren() as $process) {
                $iid = $process->getInternalId();
                if ($process->isAlive()) {
                    echo sprintf('Process %s is running', $iid) . PHP_EOL;
                } else if ($process->isFinished()) {
                    echo sprintf('Process %s is finished', $iid) . PHP_EOL;
                }
            }
            sleep(1);
        } while ($manager->countAliveChildren());

    }

    /**
     * 判断定时任务是否到时
     * @return array
     */
    protected function getMission()
    {
        $cur_time = time();
        $mission = [];
        foreach ($this->config as $config) {
            if ($cur_time - CrontabParse::parse($config['time_rule']) == 0) {
                $mission[] = $config;
            }
        }

        return $mission;
    }

    /**
     * 设置日志文件
     * @param $filename
     */
    public function setLogFile($filename)
    {
        $this->log_file = $filename;
    }

    /**
     * 日志记录
     * @param $cmd
     */
    protected function log($cmd)
    {
        $content = '[' . date('Y-m-d H:i:s') . ']-' . '.cmd:' . $cmd . PHP_EOL;
        if (is_file($this->log_file) && is_writable($this->log_file)) {
            file_put_contents($this->log_file, $content, FILE_APPEND);
        }
    }
} 