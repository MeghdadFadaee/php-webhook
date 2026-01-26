<?php
session_start();

define("APP_START_AT", microtime(true));

define("BASE_PATH", realpath('..'));
define("APP_PATH", realpath('../app'));
define("PUBLIC_PATH", realpath('../public'));
define("STORAGE_PATH", realpath('../storage'));

require_once '../app/helpers.php';
require_once '../app/Enums/HttpStatus.php';
require_once '../app/Http/Request.php';
require_once '../app/Http/Response.php';
require_once '../app/Http/JsonResponse.php';
require_once '../app/Http/MainController.php';
require_once '../app/Support/Arr.php';
require_once '../app/Support/Str.php';
require_once '../app/Support/Collection.php';
require_once '../app/Support/Logger.php';
require_once '../app/Support/Storage.php';
require_once '../app/Service/Client.php';
require_once '../app/Service/ClientResponse.php';

Logger::request(sanitize: environment('local'));
Storage::link();

$hooks = [
    '/' => MainController::class,
    '*' => fn(Request $request) =>  JsonResponse::successful('service is running!')->send()
];

Request::capture()->pipe($hooks);
