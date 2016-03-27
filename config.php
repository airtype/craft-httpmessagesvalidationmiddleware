<?php

namespace Craft;

$config = [

];

foreach ($config as $key => $value) {
    craft()->config->set($key, $value, 'httpmessagesvalidationmiddleware');
}
