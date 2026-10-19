<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satuan fisik terkecil hasil receiving (1 record = 1 unit UOM).
 * kode_uom_serialno: {SKU}-{UOM}-{RECEIVE_NO}-{SEQ:003}
 */
class ItemSerial extends Model
{
    protected $primaryKey = 'kode_uom_serialno';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kode_uom_serialno',
        'sku',
        'uom',
        'location_id',
        'receive_no',
        'po_no',
        'supplier',
        'qty_uom_in',
        'qty_smallest_unit_in',
        'bin_no',
        'weight',
        'input_receive_by',
        'input_receive_date_time',
        'pick_no',
        'so_no',
        'qty_uom_out',
        'qty_smallest_unit_out',
        'input_pick_by',
        'input_pick_date_time',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'qty_uom_in' => 'integer',
        'qty_smallest_unit_in' => 'float',
        'weight' => 'float',
        'qty_uom_out' => 'integer',
        'qty_smallest_unit_out' => 'float',
        'is_active' => 'boolean',
        'input_receive_date_time' => 'datetime',
        'input_pick_date_time' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'sku', 'sku');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_receive_by');
    }

    public function picker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_pick_by');
    }

    public function isPicked(): bool
    {
        return (int) $this->qty_uom_out > 0;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Payload detail untuk hasil scan / lookup (mobile, inbound, picking).
     *
     * @return array<string, mixed>
     */
    public function toLookupPayload(): array
    {
        return [
            'type' => 'serial',
            'kode_uom_serialno' => $this->kode_uom_serialno,
            'sku' => $this->sku,
            'item' => $this->item?->name,
            'uom' => $this->uom,
            'qty_uom_in' => (int) $this->qty_uom_in,
            'qty_smallest_unit_in' => (float) $this->qty_smallest_unit_in,
            'receive_no' => $this->receive_no,
            'po_no' => $this->po_no,
            'supplier' => $this->supplier,
            'bin_no' => $this->bin_no,
            'weight' => $this->weight,
            'location' => $this->location?->code,
            'picked' => $this->isPicked(),
            'pick_no' => $this->pick_no,
            'so_no' => $this->so_no,
            'qty_uom_out' => (int) $this->qty_uom_out,
            'qty_smallest_unit_out' => (float) $this->qty_smallest_unit_out,
            'is_active' => (bool) $this->is_active,
            'receive_at' => $this->input_receive_date_time?->format('d/m/Y H:i'),
            'pick_at' => $this->input_pick_date_time?->format('d/m/Y H:i'),
            'notes' => $this->notes,
        ];
    }

    /**
     * Generate serial per baris penerimaan: 1 record = 1 unit UOM fisik.
     * Sequence reset per penerimaan dan per kombinasi SKU + UOM.
     * Idempoten: aman dipanggil lebih dari sekali untuk dokumen yang sama.
     */
    public static function generateForReceipt(GoodsReceipt $receipt): int
    {
        if (static::where('receive_no', $receipt->doc_number)->exists()) {
            return 0;
        }

        $created = 0;
        $sequence = [];

        foreach ($receipt->items()->with(['item', 'receivedUom', 'targetBin'])->get() as $line) {
            $qty = (int) floor((float) $line->received_qty + 0.0001);

            if ($qty <= 0 || ! $line->item) {
                continue;
            }

            $sku = $line->item->sku;
            $uom = $line->receivedUom?->code ?: 'PCS';
            $location = $line->targetBin ?: $receipt->location;
            $overrides = $line->unit_overrides ?? [];
            $key = $sku.'|'.$uom;
            $sequence[$key] = $sequence[$key] ?? 0;

            for ($i = 0; $i < $qty; $i++) {
                $sequence[$key]++;
                $override = is_array($overrides[$i] ?? null) ? $overrides[$i] : [];

                static::create([
                    'kode_uom_serialno' => sprintf('%s-%s-%s-%03d', $sku, $uom, $receipt->doc_number, $sequence[$key]),
                    'sku' => $sku,
                    'uom' => $uom,
                    'location_id' => $location?->id,
                    'receive_no' => $receipt->doc_number,
                    'po_no' => $receipt->po_no,
                    'supplier' => $receipt->supplier?->name,
                    'qty_uom_in' => 1,
                    'qty_smallest_unit_in' => (float) $line->conversion_multiplier,
                    'bin_no' => ($override['bin_no'] ?? null) ?: ($line->bin_no ?: $location?->code),
                    'weight' => ($override['weight'] ?? null) ?? $line->weight,
                    'input_receive_by' => $receipt->user_id ?? auth()->id(),
                    'input_receive_date_time' => $receipt->received_at ?? now(),
                    'notes' => $line->notes ?: $receipt->notes,
                ]);

                $created++;
            }
        }

        return $created;
    }
}
