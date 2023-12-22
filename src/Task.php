<?php

namespace Wpjscc\Task;

use React\ChildProcess\Process;
use MessagePack\MessagePack;
use Laravel\SerializableClosure\SerializableClosure;
use MessagePack\BufferUnpacker;
use Evenement\EventEmitter;

class Task
{
    public static $processNumber = 1;

    protected static $stdout;

    protected static $processes = [];
    protected static $unpackers = [];
    protected static $taskProcessInit = [];
    protected static $taskProcessEvents = [];

    protected static $taskEvents = [];



    protected static $running = false;

    public static function run($php = null)
    {
        if (static::$running) {
            return;
        }

        static::$running = true;
        static::$stdout = new \React\Stream\WritableResourceStream(STDOUT);

        for ($i = 0; $i < self::$processNumber; $i++) {
            static::runProcess($php);
        }
    }

    protected static function runProcess($php = null)
    {
        $process = new Process('exec ' . ($php ?: 'php') . ' ' . __DIR__ . '/init.php');
        $process->start();

        static::$processes[$process->getPid()] = $process;
        static::$unpackers[$process->getPid()] = new BufferUnpacker;
        static::$taskProcessEvents[$process->getPid()] = new EventEmitter;

        $process->stdout->on('data', function ($chunk) use ($process) {
            $unpacker = static::$unpackers[$process->getPid()];
            $unpacker->append($chunk);
            if ($messages = $unpacker->tryUnpack()) {
                $unpacker->release();
                foreach ($messages as $message) {
                    if (is_array($message)) {
                        if (isset($message['cmd'])) {
                            static::getStdout()->write(json_encode($message, JSON_UNESCAPED_UNICODE)."\n");
                            if ($message['cmd'] == 'init') {
                                static::$taskProcessInit[$process->getPid()] = true;
                                static::$taskProcessEvents[$process->getPid()]->emit('init', [$message['data'] ?? []]);
                            }
                            elseif (in_array($message['cmd'] , [
                                'data',
                                'success',
                                'fail',
                                'end',
                            ])) {
                                if (isset(static::$taskEvents[$message['uuid']])) {
                                    $event = static::$taskEvents[$message['uuid']];
                                    $event->emit($message['cmd'], [$message['data']]);
                                    if (in_array($message['cmd'], ['success','fail'])) {
                                        unset(static::$taskEvents[$message['uuid']]);
                                    }
                                }
                            } 
                            elseif ($message['cmd'] == 'log') {
                                //static::getStdout()->write(json_encode($message, JSON_UNESCAPED_UNICODE)."\n");
                            }
                        }

                    }
                }

                return $messages;
            }
        });


        $process->stdout->on('end', function () {
            echo "end\n";
        });

        $process->stdout->on('error', function (\Exception $e) {
            echo 'error: ' . $e->getMessage();
        });

        $process->stdout->on('close', function () {
            echo "closed\n";
        });


        $process->on('exit', function ($exitCode, $termSignal) use ($process, $php) {
            echo "Process exited with code $exitCode\n";
            unset(static::$processes[$process->getPid()]);
            unset(static::$unpackers[$process->getPid()]);
            unset(static::$taskProcessInit[$process->getPid()]);
            unset(static::$taskProcessEvents[$process->getPid()]);
            static::runProcess($php);
        });
    }

    public static function addTask($closure)
    {
        if (!static::$running) {
            static::run();
        }

        $serialized = static::getSeralized($closure);
        // 随机一个进程
        $process = static::$processes[array_rand(static::$processes)];
        $uuid = $process->getPid() . '-' . time() . '-' . uniqid() . '-' . md5($serialized);
        $event = new EventEmitter;
        static::$taskEvents[$uuid] = $event;

        $pack = MessagePack::pack([
            'cmd' => 'task',
            'uuid' => $uuid,
            'data' => [
                'serialized' => $serialized
            ]
        ]);

        if (!isset(static::$taskProcessInit[$process->getPid()])) {
            static::$taskProcessEvents[$process->getPid()]->once('init', function () use ($process, $pack) {
                $process->stdin->write($pack);
            });
        } else {
            $process->stdin->write($pack);
        }

        return $event;
    }

    public static function getSeralized($closure)
    {
        return serialize(new SerializableClosure($closure));
    }

    public static function handleTask($message)
    {
        $serialized = $message['data']['serialized'];
        $closure = unserialize($serialized)->getClosure();
        $uuid = $message['uuid'];
        static::replayStart([
            'msg' => 'start handle task',
        ]);

        $data = $closure($uuid);

        if ($data instanceof EventEmitter) {
            $data->on('data', function ($data) use ($uuid) {
                static::replayData($uuid, $data);
            });
            $data->on('success', function ($data) use ($uuid) {
                static::replaySuccess($uuid, $data);
            });
            $data->on('fail', function ($data) use ($uuid) {
                static::replayFail($uuid, $data);
            });
        } else {
            if ($data === false) {
                static::replayFail($uuid);
                static::replayLog([
                    'msg' => 'fail handle task',
                    'uuid' => $uuid,
                ]);
            } else {
                static::replaySuccess($uuid, $data);
            }
        }

    }



    // 给父进程回复
    public static function replayStart($uuid, $data = null)
    {
        static::replay([
            'cmd' => 'start',
            'uuid' => $uuid,
            'data' => $data
        ]);
    }

    public static function replayData($uuid, $data)
    {
        static::replay([
            'cmd' => 'data',
            'uuid' => $uuid,
            'data' => $data
        ]);
    }

    public static function replaySuccess($uuid, $data = null)
    {
        static::replay([
            'cmd' => 'success',
            'uuid' => $uuid,
            'data' => $data
        ]);
    }
    public static function replayFail($uuid, $data = null)
    {
        static::replay([
            'cmd' => 'fail',
            'uuid' => $uuid,
            'data' => $data
        ]);
    }
    public static function replayEnd($uuid, $data = null)
    {
        static::replay([
            'cmd' => 'end',
            'uuid' => $uuid,
            'data' => $data
        ]);
    }

    public static function replayInit($extra = [])
    {
        $pid = getmypid();
        static::replay([
            'cmd' => 'init',
            'data' => [
                'msg' => "Task Process {$pid} init success!\n",
                'extra' => $extra
            ]
        ]);
    }
    public static function replayLog($data)
    {
        static::replay([
            'cmd' => 'log',
            'data' => $data
        ]);
    }

    public static function replay($data)
    {
        static::getStdout()->write(MessagePack::pack($data));
    }


    public static function getStdout()
    {
        if (static::$stdout) {
            return static::$stdout;
        }
        return static::$stdout = new \React\Stream\WritableResourceStream(STDOUT);
    }
}
