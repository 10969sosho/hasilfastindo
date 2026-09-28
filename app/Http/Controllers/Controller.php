<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Item;
use App\Models\ItemUomConversion;
use App\Models\Location;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

abstract class Controller
{
    /* ------------------------------------------------------------------
     |  Scope cabang: superadmin/pusat bisa lihat semua, staf cabang terbatas
     ------------------------------------------------------------------ */

    protected function canSeeAllBranches(): bool
    {
        $user = auth()->user();

        if (! $user || ! in_array($user->role, ['super_admin', 'central'], true)) {
            return false;
        }

        return session('active_branch_id') === null;
    }

    protected function activeBranchId(): ?int
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return (int) (session('active_branch_id') ?: $user->branch_id);
    }

    protected function canSwitchBranch(): bool
    {
        $user = auth()->user();

        return $user && in_array($user->role, ['super_admin', 'central'], true);
    }

    /**
     * Batasi query hanya ke cabang yang boleh diakses user.
     *
     * @param  Builder  $query
     * @return Builder
     */
    protected function scopeBranch($query, string $column = 'branch_id')
    {
        if ($this->canSeeAllBranches()) {
            return $query;
        }

        $branchId = $this->activeBranchId();

        if ($branchId) {
            $query->where($column, $branchId);
        }

        return $query;
    }

    protected function guardBranch(?int $branchId): void
    {
        if ($this->canSeeAllBranches() || $branchId === null || $branchId === $this->activeBranchId()) {
            return;
        }

        abort(403, 'Anda tidak memiliki akses ke cabang tersebut.');
    }

    /**
     * Semua cabang tanpa penyaringan per user.
     *
     * @return Collection<int, Branch>
     */
    protected function allBranches()
    {
        return Branch::orderBy('is_central', 'desc')->orderBy('name')->get();
    }

    protected function branchesForUser()
    {
        if ($this->canSeeAllBranches()) {
            return $this->allBranches();
        }

        return Branch::whereIn('id', array_filter([$this->activeBranchId()]))->get();
    }

    /* ------------------------------------------------------------------
     |  Penomoran dokumen
     ------------------------------------------------------------------ */

    protected function nextDocNumber(string $prefix, string $table, string $column, int $pad = 4): string
    {
        $seq = DB::table($table)->where($column, 'like', $prefix.'%')->count() + 1;

        do {
            $number = $prefix.sprintf("%0{$pad}d", $seq);
            $seq++;
        } while (DB::table($table)->where($column, $number)->exists());

        return $number;
    }

    /* ------------------------------------------------------------------
     |  Konversi satuan
     ------------------------------------------------------------------ */

    protected function conversionMultiplier(int $itemId, int $fromUomId, int $toUomId): ?float
    {
        if ($fromUomId === $toUomId) {
            return 1.0;
        }

        $direct = ItemUomConversion::where('item_id', $itemId)
            ->where('from_uom_id', $fromUomId)
            ->where('to_uom_id', $toUomId)
            ->value('multiplier');

        if ($direct !== null) {
            return (float) $direct;
        }

        $reverse = ItemUomConversion::where('item_id', $itemId)
            ->where('from_uom_id', $toUomId)
            ->where('to_uom_id', $fromUomId)
            ->value('multiplier');

        if ($reverse) {
            return 1 / (float) $reverse;
        }

        return null;
    }

    /**
     * Konversi qty ke satuan dasar (PCS).
     */
    protected function toPcs(int $itemId, float $qty, int $fromUomId): float
    {
        $baseUomId = (int) Item::query()->whereKey($itemId)->value('base_uom_id');

        return (float) ($this->conversionMultiplier($itemId, $fromUomId, $baseUomId) ?? 1) * $qty;
    }

    /* ------------------------------------------------------------------
     |  Gudang & BIN helper
     ------------------------------------------------------------------ */

    protected function defaultWarehouse(int $branchId): ?Warehouse
    {
        return Warehouse::where('branch_id', $branchId)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->first();
    }

    protected function locationsOf(?int $warehouseId)
    {
        if (! $warehouseId) {
            return collect();
        }

        return Location::where('warehouse_id', $warehouseId)->orderBy('code')->get();
    }
}
