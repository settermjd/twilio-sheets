<?php

declare(strict_types=1);

use App\Handler\TwilioSheetsHandler;
use App\Service\{GoogleSheetsService,OrderDeskService};
use Monolog\{Level,Logger};
use Monolog\Handler\StreamHandler;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
$dotenv->load();

$gsheetsService = new GoogleSheetsService(
    $_ENV['GOOGLE_API_KEY'],
    $_ENV['GOOGLE_SERVICE_ACCOUNT_NAME'],
);
$logger = new Logger("app-log")->pushHandler(
    new StreamHandler(__DIR__ . "/../data/log/app.log", Level::Debug),
);
$orderDeskService = new OrderDeskService(
    $_ENV['ORDERDESK_STORE_ID'],
    $_ENV['ORDERDESK_API_KEY'],
);

$app = AppFactory::create();
$app->post(
    '/update',
    new TwilioSheetsHandler(
        $orderDeskService,
        $gsheetsService,
        $_ENV["SPREADSHEET_ID"],
        $logger,
    ),
);
$app->run();
