<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Delivery extends Model
{
    public const STATUSES = [
        'pending_scan', 'scanned_out', 'in_delivery', 'arrived', 'received', 'problem',
    ];

    protected $fillable = [
        'delivery_no',
        'sales_order_id',
        'packing_list_id',
        'branch_id',
        'vehicle_id',
        'driver_id',
        'helper_id',
        'driver_name',
        'helper_name',
        'destination_address',
        'method',
        'departure_time',
        'arrival_time',
        'status',
        'received_by',
        'received_notes',
        'received_photo',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'departure_time' => 'datetime',
        'arrival_time' => 'datetime',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function packingList(): BelongsTo
    {
        return $this->belongsTo(PackingList::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'driver_id');
    }

    public function helper(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'helper_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function tracks(): HasMany
    {
        return $this->hasMany(DeliveryTrack::class);
    }
}
