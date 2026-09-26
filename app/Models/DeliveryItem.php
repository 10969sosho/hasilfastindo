<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryItem extends Model
{
    protected $fillable = [
        'delivery_id',
        'packing_list_box_id',
        'item_id',
        'batch_id',
        'qty',
        'uom_id',
        'barcode',
        'scanned_out_at',
        'status',
    ];

    protected $casts = [
        'qty' => 'float',
        'scanned_out_at' => 'datetime',
    ];

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function box(): BelongsTo
    {
        return $this->belongsTo(PackingListBox::class, 'packing_list_box_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }
}
