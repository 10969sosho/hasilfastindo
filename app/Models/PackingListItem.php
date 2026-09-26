<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackingListItem extends Model
{
    protected $fillable = [
        'packing_list_id',
        'packing_list_box_id',
        'item_id',
        'batch_id',
        'sales_order_item_id',
        'qty',
        'uom_id',
    ];

    protected $casts = [
        'qty' => 'float',
    ];

    public function packingList(): BelongsTo
    {
        return $this->belongsTo(PackingList::class);
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

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }
}
