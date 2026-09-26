<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ['branch_id', 'code', 'name', 'is_default'];

    protected $casts = ['is_default' => 'boolean'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function locations()
    {
        return $this->hasMany(Location::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }
}
