<?php
session_start();

define("APP_START_AT", microtime(true));
define("BASE_PATH", realpath(''));

require 'Str.php';
require 'Arr.php';
require 'HttpStatus.php';
require 'Request.php';
require 'Response.php';
require 'JsonResponse.php';
require 'Client.php';
require 'ClientResponse.php';

$hooks = [
    // '/*' => MainController::class,
    '*' => fn(Request $request) => JsonResponse::successful('service is running!')->send()
];

Request::capture()->pipe($hooks);
