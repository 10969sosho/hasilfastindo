<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackingListBox extends Model
{
    protected $fillable = [
        'packing_list_id',
        'box_number',
        'box_barcode',
        'weight_kg',
        'status',
    ];

    protected $casts = [
        'box_number' => 'integer',
        'weight_kg' => 'float',
    ];

    public function packingList(): BelongsTo
    {
        return $this->belongsTo(PackingList::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackingListItem::class);
    }

    public function deliveryItems(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }
}
