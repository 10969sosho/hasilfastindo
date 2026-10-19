<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferScan extends Model
{
    protected $fillable = [
        'stock_transfer_id',
        'kode_uom_serialno',
        'action',
        'user_id',
    ];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function serial(): BelongsTo
    {
        return $this->belongsTo(ItemSerial::class, 'kode_uom_serialno');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
