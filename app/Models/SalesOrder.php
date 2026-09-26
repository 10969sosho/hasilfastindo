<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    public const STATUSES = ['draft', 'pending', 'processing', 'partial', 'completed', 'cancelled'];

    protected $fillable = [
        'so_number',
        'customer_id',
        'branch_id',
        'fulfillment_branch_id',
        'order_date',
        'status',
        'total_amount',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'order_date' => 'date',
        'total_amount' => 'float',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function fulfillmentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'fulfillment_branch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function packingLists(): HasMany
    {
        return $this->hasMany(PackingList::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
