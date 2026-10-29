<?php

namespace App\Http\Controllers;

use App\Services\BusinessCentralService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class NavExplorerController extends Controller
{
    public function index(Request $request, BusinessCentralService $service): View
    {
        $tenant = config('services.business_central.tenant_id');
        $env = config('services.business_central.environment');
        $company = rawurlencode(config('services.business_central.company'));

        $jsonPath = storage_path('app/all_nav_tables.json');
        if (file_exists($jsonPath)) {
            $tableNames = json_decode(file_get_contents($jsonPath), true) ?: [];
        } else {
            $reflection = new \ReflectionClass($service);
            $method = $reflection->getMethod('request');
            $method->setAccessible(true);
            $endpoints = $method->invoke($service, "https://api.businesscentral.dynamics.com/v2.0/{$tenant}/{$env}/ODataV4");
            $tableNames = collect($endpoints)->pluck('name')->sort()->values()->all();
            file_put_contents($jsonPath, json_encode($tableNames, JSON_PRETTY_PRINT));
        }

        $selectedTable = $request->query('table', 'Power_BI_Purchase_List');
        $tableData = [];
        $columns = [];
        $errorMessage = null;

        if ($selectedTable) {
            try {
                $reflection = new \ReflectionClass($service);
                $method = $reflection->getMethod('request');
                $method->setAccessible(true);
                $url = "https://api.businesscentral.dynamics.com/v2.0/{$tenant}/{$env}/ODataV4/Company('{$company}')/{$selectedTable}";
                $res = $method->invoke($service, $url, ['$top' => 10]);
                $tableData = $res instanceof Collection ? $res->toArray() : (array) $res;

                if (! empty($tableData)) {
                    $firstRow = (array) $tableData[0];
                    $columns = array_keys($firstRow);
                }
            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();
            }
        }

        return view('nav-explorer.index', [
            'tables' => $tableNames,
            'selectedTable' => $selectedTable,
            'tableData' => $tableData,
            'columns' => $columns,
            'errorMessage' => $errorMessage,
        ]);
    }
}
