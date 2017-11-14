<?php
require_once 'tariffRP.class.php';

$tf = new tariffRP();
$tf ->setIsAvia(true)
    ->setWeight(1000)
    ->setDestination('AUT')
    ->setDeclareValue(15000)
    ->setType('EMS');

echo $tf->getCost();
