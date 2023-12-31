<?php

require __DIR__ . '/../vendor/autoload.php';

use Wpjscc\Task\Task;

Task::$processNumber = 4;

// Task::run();

$event = Task::addTask(function ($uuid) {
    Task::replayData($uuid, 'hello world');
    Task::replayFail($uuid, 'task is fail');
    return false;
});
$event->on('data', function ($data) {
    echo ($data) . "\n";
});

$event->once('fail', function ($data) {
    echo ($data) . "\n";
});
