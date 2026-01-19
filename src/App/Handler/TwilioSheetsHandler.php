<?php

declare(strict_types=1);

namespace App\Handler;

use App\Service\{GoogleSheetsService,OrderDeskService};
use Psr\Http\Message\{ResponseInterface,ServerRequestInterface};
use Psr\Log\LoggerInterface;

final class TwilioSheetsHandler
{
    public const int DEFAULT_START_ROW = 2;
    public const string SHEET_RANGE_PATTERN = "Sheet1!A%d:F6";

    public function __construct(
        private OrderDeskService $orderDeskService,
        private GoogleSheetsService $googleSheetsService,
        private string $spreadsheetId,
        private ?LoggerInterface $logger = null,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response)
    {
        $orders = $this->orderDeskService->get("orders");
        $values   = [];

        if (!empty($orders["orders"])) {
            $this->logger?->debug("Retrieved orders", $orders["orders"]);

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

            $this->storeRecordsInGoogleSheets($values);
        }

        return $response;
    }

    private function storeRecordsInGoogleSheets(array $sheetData)
    {
        $rows = $this->googleSheetsService->getRows($this->spreadsheetId);
        $startRow = count($rows->getValues()) === 1
            ? self::DEFAULT_START_ROW
            : count($rows->getValues()) + 1;

        $result = $this->googleSheetsService->addRow(
            $sheetData,
            sprintf(self::SHEET_RANGE_PATTERN, $startRow),
            $this->spreadsheetId,
        );

        return $result->getUpdates();
    }
}
