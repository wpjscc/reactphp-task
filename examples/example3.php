<?php

require __DIR__ . '/../vendor/autoload.php';

use Wpjscc\Task\Task;
use Evenement\EventEmitter;
use React\EventLoop\Loop;

$closure = function ($uuid) {
    $event = new EventEmitter();
    $timer = Loop::addPeriodicTimer(1, function () use ($event, $uuid) {
        $event->emit('data', ['hello world']);
    });
    Loop::addTimer(10, function () use ($event, $timer) {
        Loop::cancelTimer($timer);
        $event->emit('success', ['hello world  success']);
    });
    return $event;
};
Task::run();

function msg($msg){
    if (is_array($msg)) {
        $msg = json_encode($msg);
    }
    Task::getStdout()->write($msg."\n");
}

$event = Task::addTask($closure);

$event->on('data', function ($data) {
    // var_dump($data);
    msg($data);
});

$event->on('success', function ($data) {
    msg($data);
});