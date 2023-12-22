<?php

require __DIR__ . '/../vendor/autoload.php';

use Wpjscc\Task\Task;

$closure = function ($uuid) {
    Task::replayData($uuid, 'hello world');
    return 'james';
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