<?php

require __DIR__ . '/../vendor/autoload.php';

use Wpjscc\Task\Task;
use Evenement\EventEmitter;
use React\EventLoop\Loop;

Task::$processNumber = 4;


$event = Task::addTask(function ($uuid) {
    $event = new EventEmitter();
    $timer = Loop::addPeriodicTimer(1, function () use ($event, $uuid) {
        $event->emit('data', ['hello world']);
    });
    Loop::addTimer(10, function () use ($event, $timer) {
        Loop::cancelTimer($timer);
        $event->emit('success', ['hello world  success']);
    });
    return $event;
});

$event->on('data', function ($data) {
    echo ($data)."\n";
});

$event->on('success', function ($data) {
    echo ($data)."\n";
});