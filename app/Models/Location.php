<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ['warehouse_id', 'code', 'area', 'rack', 'shelf', 'bin', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
}
