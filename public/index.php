<?php

declare(strict_types=1);

use App\Service\GoogleSheetsService;
use App\Service\OrderDeskService;
use DI\Container;
use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . "/../");
$dotenv->load();

$container = new Container();
$container->set(
    GoogleSheetsService::class,
    function () {
        return new GoogleSheetsService(
            $_ENV['GOOGLE_API_KEY'],
            $_ENV['GOOGLE_SERVICE_ACCOUNT_NAME'],
        );
    },
);
$container->set(
    OrderDeskService::class,
    fn() => new OrderDeskService($_ENV['ORDERDESK_STORE_ID'], $_ENV['ORDERDESK_API_KEY']),
);
$container->set(
    LoggerInterface::class,
    fn() => new Logger("app-log")->pushHandler(
        new StreamHandler(__DIR__ . "/../data/log/app.log", Level::Debug),
    ),
);
AppFactory::setContainer($container);

$app = AppFactory::create();

$app->get(
    '/update',
    function (Request $request, Response $response) {
        /* @var GoogleSheetsService $service */
        $service = $this->get(GoogleSheetsService::class);
        $values = $service->getRows($_ENV['SPREADSHEET_ID']);

        $response->getBody()->write(json_encode($values->getValues()));

        return $response;
    },
);

$app->post(
    '/update',
    function (Request $request, Response $response) {
        /* @var OrderDeskService $orderDesk */
        $orderDesk = $this->get(OrderDeskService::class);

        $orders = $orderDesk->get("orders");
        $values   = [];

        if (!empty($orders["orders"])) {
            foreach ($orders["orders"] as $order) {
                $value = [
                    $order["shipping"]["first_name"],
                    $order["shipping"]["last_name"],
                    $order["email"],
                    $order["shipping_method"],
                    $order["payment_type"],
                    $order["order_total"],
                ];
                array_push($values, $value);
            }

            storeRecordsInGoogleSheets(
                $this->get(GoogleSheetsService::class),
                $values,
            );
        }

        return $response;
    },
);

function storeRecordsInGoogleSheets(GoogleSheetsService $sheetsService, array $sheetData)
{
    $rows = $sheetsService->getRows($_ENV['SPREADSHEET_ID']);

    $startRow = count($rows->getValues()) === 1
        ? 2
        : count($rows->getValues()) + 1;

    $range = sprintf("Sheet1!A%d:F6", $startRow);

    /* @var \Google\Service\Sheets\AppendValuesResponse $result */
    $result = $sheetsService->addRow($sheetData, $range, $_ENV['SPREADSHEET_ID']);

    return $result->getUpdates();
}

$app->run();
