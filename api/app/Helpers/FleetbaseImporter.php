<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;

class FleetbaseImporter
{
    protected string $apiKey;
    protected string $baseUrl;
    protected bool $dryRun;
    protected array $companyMap = [];

    public function __construct(string $apiKey, string $baseUrl = 'https://api.fleetbase.io', bool $dryRun = false)
    {
        $this->apiKey  = $apiKey;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->dryRun  = $dryRun;
    }

    /**
     * Import companies from row data.
     *
     * @param  array  $rows
     * @return array  ['created' => int, 'skipped' => int, 'errors' => array]
     */
    public function importCompanies(array $rows): array
    {
        return $this->importRecords($rows, 'organizations', function (array $row) {
            return array_filter([
                'name'    => $row['name'] ?? $row['company_name'] ?? null,
                'email'   => $row['email'] ?? null,
                'phone'   => $row['phone'] ?? $row['phone_number'] ?? null,
                'country' => $row['country'] ?? null,
                'website' => $row['website'] ?? null,
            ]);
        }, 'name');
    }

    /**
     * Import users from row data.
     */
    public function importUsers(array $rows): array
    {
        return $this->importRecords($rows, 'users', function (array $row) {
            return array_filter([
                'name'     => $row['name'] ?? trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'email'    => $row['email'] ?? null,
                'phone'    => $row['phone'] ?? $row['phone_number'] ?? null,
                'password' => $row['password'] ?? bin2hex(random_bytes(12)),
            ]);
        }, 'email');
    }

    /**
     * Import vehicles from row data.
     */
    public function importVehicles(array $rows): array
    {
        return $this->importRecords($rows, 'vehicles', function (array $row) {
            return array_filter([
                'make'         => $row['make'] ?? null,
                'model'        => $row['model'] ?? null,
                'year'         => $row['year'] ?? null,
                'trim'         => $row['trim'] ?? null,
                'plate_number' => $row['plate_number'] ?? $row['registration'] ?? $row['plate'] ?? null,
                'vin'          => $row['vin'] ?? null,
                'status'       => $row['status'] ?? 'active',
            ]);
        }, 'plate_number');
    }

    /**
     * Import places/branches from row data.
     */
    public function importBranches(array $rows): array
    {
        return $this->importRecords($rows, 'places', function (array $row) {
            return array_filter([
                'name'    => $row['name'] ?? $row['branch_name'] ?? null,
                'phone'   => $row['phone'] ?? null,
                'email'   => $row['email'] ?? null,
                'address' => $row['address'] ?? $row['street_address'] ?? null,
                'city'    => $row['city'] ?? null,
                'country' => $row['country'] ?? null,
            ]);
        }, 'name');
    }

    /**
     * Import service rates / tariffs from row data.
     */
    public function importTariffs(array $rows): array
    {
        return $this->importRecords($rows, 'service-rates', function (array $row) {
            return array_filter([
                'service_name'   => $row['name'] ?? $row['tariff_name'] ?? $row['service_name'] ?? null,
                'base_fee'       => isset($row['base_fee']) ? (float) $row['base_fee'] : (isset($row['amount']) ? (float) $row['amount'] : null),
                'rate_per_km'    => isset($row['rate_per_km']) ? (float) $row['rate_per_km'] : null,
                'currency'       => $row['currency'] ?? 'USD',
            ]);
        }, 'service_name');
    }

    /**
     * Generic record importer.
     */
    protected function importRecords(array $rows, string $endpoint, callable $mapper, string $uniqueKey): array
    {
        $result = ['created' => 0, 'skipped' => 0, 'errors' => []];

        foreach ($rows as $row) {
            $payload = $mapper($row);

            if (empty($payload[$uniqueKey])) {
                $result['skipped']++;
                continue;
            }

            if ($this->dryRun) {
                $result['created']++;
                continue;
            }

            try {
                $response = $this->post($endpoint, $payload);

                if (isset($response['id']) || isset($response['data']['id'])) {
                    $result['created']++;

                    // Track company IDs for relationship mapping
                    if ($endpoint === 'organizations' && !empty($payload['name'])) {
                        $id = $response['id'] ?? $response['data']['id'];
                        $this->companyMap[$payload['name']] = $id;
                    }
                } else {
                    $result['skipped']++;
                }
            } catch (\Exception $e) {
                $result['errors'][] = $e->getMessage();
                Log::error("FleetbaseImporter [{$endpoint}] error: " . $e->getMessage(), ['row' => $row]);
            }
        }

        return $result;
    }

    /**
     * POST to Fleetbase API.
     */
    protected function post(string $endpoint, array $payload): array
    {
        $url = "{$this->baseUrl}/v1/{$endpoint}";
        $body = json_encode($payload);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \RuntimeException("cURL error: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($httpCode === 409 || (isset($decoded['error']) && str_contains(strtolower($decoded['error'] ?? ''), 'duplicate'))) {
            return ['skipped' => true];
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $msg = $decoded['message'] ?? $decoded['error'] ?? "HTTP {$httpCode}";
            throw new \RuntimeException($msg);
        }

        return $decoded ?? [];
    }
}
