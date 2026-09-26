<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackingList extends Model
{
    protected $fillable = [
        'no_packing',
        'sales_order_id',
        'customer_id',
        'branch_id',
        'source_branch_id',
        'total_box',
        'status',
        'user_id',
        'notes',
    ];

    protected $casts = [
        'total_box' => 'integer',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sourceBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'source_branch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function boxes(): HasMany
    {
        return $this->hasMany(PackingListBox::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PackingListItem::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}
