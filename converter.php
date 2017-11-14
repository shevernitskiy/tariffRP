<?php

$ar = json_decode(file_get_contents(dirname(__FILE__).'\country.json'), true);
$res = array();
$i = 0;
foreach ($ar as $key => $row) {
    if (array_key_exists('emsZone',$row)) {
        $res[$i] = array('country' => strtolower($row['code3']));
        $i++;

    }
}

echo '<pre>';
print_r($res);
echo '</pre>';
file_put_contents('allowedAddress.json',json_encode($res));
