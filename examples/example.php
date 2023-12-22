<?php

require __DIR__ . '/../vendor/autoload.php';

use Laravel\SerializableClosure\SerializableClosure;

$a = ' world';
$closure = function () use ($a) {
    $b = $a;
    return 'james'.$b;
};

// Recommended
// SerializableClosure::setSecretKey('secret');

$serialized = serialize(new SerializableClosure($closure));
$closure = unserialize($serialized)->getClosure();

echo $closure(); // james;
echo $serialized;