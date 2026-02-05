<?php
session_start();

define("APP_START_AT", microtime(true));

define("BASE_PATH", realpath('..'));
define("APP_PATH", realpath('../app'));
define("PUBLIC_PATH", realpath('../public'));
define("STORAGE_PATH", realpath('../storage'));

require_once '../app/helpers.php';
define("APP_CONTAINER", makeAppContainer());

spl_autoload_register(function ($class) {
    if (isset(APP_CONTAINER[$class])) {
        require APP_CONTAINER[$class];
    }
});

Logger::request(sanitize: environment('local'));
Storage::link();

$hooks = [
    '/*' => MainController::class,
    '*' => fn(Request $request) => JsonResponse::successful('service is running!')->send()
];

Request::capture()->pipe($hooks);
