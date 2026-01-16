<?php

declare(strict_types=1);

namespace App\Service;

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

final class GoogleSheetsService
{
    private Sheets $sheetsService;

    public function __construct(string $apiKey, string $serviceAccountName)
    {
        $client = new Client();
        $client->setAccessType('offline');
        $client->useApplicationDefaultCredentials();
        $client->setDeveloperKey($apiKey);
        $client->setSubject($serviceAccountName);

        // Limit the application to only being able to access spreadsheets
        $client->setScopes(
            [
                'https://www.googleapis.com/auth/spreadsheets',
            ],
        );

        $this->sheetsService = new Sheets($client);
    }

    public function addRow(array $values, string $range, string $spreadsheetId)
    {
        $requestBody = new ValueRange(
            [
                'values' => $values,
            ],
        );

        $params = [
            'valueInputOption' => 'USER_ENTERED',
        ];

        return $this->sheetsService->spreadsheets_values->append(
            $spreadsheetId,
            $range,
            $requestBody,
            $params,
        );
    }

    public function getRows(string $spreadsheetId): ValueRange
    {
        return $this->sheetsService->spreadsheets_values
            ->get($spreadsheetId, "Sheet1");
    }
}
