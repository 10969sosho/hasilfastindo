<?php

namespace App\Services;

use App\Models\Item;
use App\Models\NavPurchaseOrder;
use App\Models\NavPurchaseOrderItem;
use App\Models\Supplier;
use App\Models\Uom;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BusinessCentralService
{
    protected string $tenantId;

    protected string $clientId;

    protected string $clientSecret;

    protected string $company;

    protected string $companyId;

    protected string $environment;

    protected string $tokenUrl;

    protected string $odataBaseUrl;

    protected ?string $accessToken;

    protected int $tokenExpiresAt = 0;

    public function __construct()
    {
        $config = array_merge(
            (array) config('services.businesscentral'),
            (array) config('services.business_central'),
        );

        $this->tenantId = $config['tenant_id'];
        $this->clientId = $config['client_id'];
        $this->clientSecret = $config['client_secret'];
        $this->company = $config['company_name'] ?? $config['company'];
        $this->companyId = $config['company_id'] ?? '';
        $this->environment = $config['environment'];

        $this->tokenUrl = str_replace('{tenant_id}', $this->tenantId, $config['token_url']);
        $this->odataBaseUrl = $config['odata_base_url'] ?? $this->buildOdataUrl();
    }

    protected function buildOdataUrl(): string
    {
        return 'https://api.businesscentral.dynamics.com/v2.0/'
            .$this->tenantId.'/'
            .$this->environment.'/ODataV4/Company('
            .urlencode($this->company).')';
    }

    public function getAccessToken(): string
    {
        $cacheKey = 'bc_access_token:'.$this->tenantId;

        $ttl = 3540;
        $this->accessToken = Cache::remember($cacheKey, $ttl, function () {
            return $this->fetchToken();
        });

        return $this->accessToken;
    }

    protected function fetchToken(): string
    {
        $response = Http::asForm()
            ->post($this->tokenUrl, [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'https://api.businesscentral.dynamics.com/.default',
            ]);

        if ($response->failed()) {
            Log::error('BusinessCentral OAuth failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \RuntimeException('Failed to obtain BusinessCentral access token');
        }

        $data = $response->json();

        return $data['access_token'];
    }

    public function get(string $endpoint, array $params = []): Collection
    {
        $url = rtrim($this->odataBaseUrl, '/').'/'.ltrim($endpoint, '/');

        return $this->request($url, $params);
    }

    protected function request(string $url, array $params = []): Collection
    {
        $token = $this->getAccessToken();

        $query = Http::withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])
            ->timeout(60)
            ->get($url, $params);

        if ($query->failed()) {
            Log::error('BusinessCentral API request failed', [
                'endpoint' => $url,
                'status' => $query->status(),
                'body' => substr($query->body(), 0, 500),
            ]);

            throw new \RuntimeException('BusinessCentral API request failed: '.substr($query->body(), 0, 500));
        }

        $data = $query->json();

        if (isset($data['value'])) {
            return collect($data['value']);
        }

        return collect([$data]);
    }

    public function getPaginated(string $endpoint, int $pageSize = 50): Collection
    {
        $all = collect();
        $page = 1;

        while (true) {
            $result = $this->get($endpoint, [
                '$top' => $pageSize,
                '$skip' => ($page - 1) * $pageSize,
                '$format' => 'json',
            ]);

            $all = $all->merge($result);

            if ($result->count() < $pageSize) {
                break;
            }

            $page++;
        }

        return $all;
    }

    /**
     * Tarik Purchase Order dari API v2.0 dan upsert ke tabel staging NAV.
     *
     * @return int Jumlah Purchase Order yang berhasil disinkronkan
     */
    public function syncPurchaseOrders(int $limit = 50): int
    {
        $url = sprintf(
            'https://api.businesscentral.dynamics.com/v2.0/%s/%s/api/v2.0/companies(%s)/purchaseOrders',
            $this->tenantId,
            $this->environment,
            $this->companyId,
        );

        $orders = $this->request($url, [
            '$expand' => 'purchaseOrderLines',
            '$orderby' => 'orderDate desc',
            '$top' => $limit,
        ]);

        $synced = 0;

        foreach ($orders as $order) {
            if ($this->upsertPurchaseOrder($order)) {
                $synced++;
            }
        }

        return $synced;
    }

    /**
     * @param  array<string, mixed>  $order
     */
    protected function upsertPurchaseOrder(array $order): bool
    {
        $poNo = $order['number'] ?? null;

        if (! $poNo) {
            return false;
        }

        $vendorName = $order['vendorName'] ?? '-';
        $this->syncSupplier($order['vendorNumber'] ?? null, $vendorName);

        $po = NavPurchaseOrder::firstOrNew(['po_no' => $poNo]);
        $po->order_date = $order['orderDate'] ?? null;
        $po->supplier_name = $vendorName;
        $po->status = $this->resolveStatus($po->status, $this->mapStatus($order['status'] ?? null));
        $po->notes = $order['notes'] ?? null;
        $po->save();

        $lines = $order['purchaseOrderLines']['value'] ?? $order['purchaseOrderLines'] ?? [];

        foreach ($lines as $line) {
            $sku = $line['lineObjectNumber'] ?? $line['itemNumber'] ?? null;

            if (! $sku) {
                continue;
            }

            $itemName = $line['description'] ?? $sku;
            $uomCode = substr(strtoupper($line['unitOfMeasureCode'] ?? 'PCS'), 0, 20);

            NavPurchaseOrderItem::updateOrCreate(
                ['po_id' => $po->id, 'sku' => $sku],
                [
                    'item_name' => $itemName,
                    'uom_nav' => $uomCode,
                    'nav_qty_pcs' => (float) ($line['quantity'] ?? 0),
                ],
            );

            $this->syncItemMaster($sku, $itemName, $uomCode);
        }

        return true;
    }

    protected function syncSupplier(?string $vendorNumber, string $vendorName): void
    {
        if (Supplier::where('name', $vendorName)->exists()) {
            return;
        }

        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $vendorNumber ?: $vendorName) ?: 'NAV');
        $base = substr($code, 0, 30);
        $code = $base;
        $suffix = 1;

        while (Supplier::where('code', $code)->exists()) {
            $code = substr($base, 0, 27).'-'.++$suffix;
        }

        Supplier::create(['code' => $code, 'name' => $vendorName]);
    }

    protected function syncItemMaster(string $sku, string $itemName, string $uomCode): void
    {
        if (Item::where('sku', $sku)->exists()) {
            return;
        }

        $uom = Uom::firstOrCreate(['code' => $uomCode], ['name' => $uomCode]);

        Item::create([
            'sku' => $sku,
            'name' => $itemName,
            'base_uom_id' => $uom->id,
            'is_active' => true,
        ]);
    }

    protected function mapStatus(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'released' => NavPurchaseOrder::STATUS_RELEASED,
            'completed', 'closed' => NavPurchaseOrder::STATUS_COMPLETED,
            default => NavPurchaseOrder::STATUS_OPEN,
        };
    }

    /**
     * Status penerimaan lokal (sebagian/lengkap) tidak boleh tertimpa status NAV
     * agar PO yang sama tidak bisa diterima dua kali.
     */
    protected function resolveStatus(?string $currentStatus, string $navStatus): string
    {
        if (in_array($currentStatus, [NavPurchaseOrder::STATUS_PARTIAL, NavPurchaseOrder::STATUS_COMPLETED], true)) {
            return $currentStatus;
        }

        return $navStatus;
    }
}
