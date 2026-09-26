<?php

$models = [
    'Warehouse' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ["branch_id", "code", "name", "is_default"];
    protected $casts = ["is_default" => "boolean"];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function locations() { return $this->hasMany(Location::class); }
    public function stocks() { return $this->hasMany(Stock::class); }
}
',
    'Location' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    protected $fillable = ["warehouse_id", "code", "area", "rack", "shelf", "bin", "is_active"];
    protected $casts = ["is_active" => "boolean"];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function stocks() { return $this->hasMany(Stock::class); }
}
',
    'Stock' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stock extends Model
{
    protected $fillable = [
        "branch_id", "warehouse_id", "location_id", "item_id", "batch_id", 
        "uom_id", "quantity_pcs", "original_barcode", "received_at"
    ];
    protected $casts = [
        "quantity_pcs" => "decimal:3",
        "received_at" => "datetime",
    ];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function uom() { return $this->belongsTo(Uom::class); }

    public static function allocateFifo(int $branchId, int $itemId, float $neededQty)
    {
        return self::where("branch_id", $branchId)
            ->where("item_id", $itemId)
            ->where("quantity_pcs", ">", 0)
            ->orderBy("received_at", "asc")
            ->get();
    }
}
',
    'PackingList' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingList extends Model
{
    protected $fillable = [
        "no_packing", "sales_order_id", "customer_id", "branch_id", "source_branch_id",
        "total_box", "status", "user_id", "notes"
    ];
    protected $casts = ["total_box" => "integer"];

    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function sourceBranch() { return $this->belongsTo(Branch::class, "source_branch_id"); }
    public function user() { return $this->belongsTo(User::class); }
    public function boxes() { return $this->hasMany(PackingListBox::class); }
    public function items() { return $this->hasMany(PackingListItem::class); }
}
',
    'Driver' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = ["name", "phone", "license_number", "role", "is_active"];
    protected $casts = ["is_active" => "boolean"];

    public function deliveriesAsDriver() { return $this->hasMany(Delivery::class, "driver_id"); }
    public function deliveriesAsHelper() { return $this->hasMany(Delivery::class, "helper_id"); }
}
',
    'Vehicle' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = ["plate_number", "vehicle_name", "type", "capacity_kg", "driver_id", "helper_id", "driver_name", "helper_name", "status"];

    public function driver() { return $this->belongsTo(Driver::class, "driver_id"); }
    public function helper() { return $this->belongsTo(Driver::class, "helper_id"); }
    public function deliveries() { return $this->hasMany(Delivery::class); }
}
',
];

foreach ($models as $name => $content) {
    file_put_contents("app/Models/{$name}.php", $content);
    echo "Updated Model: {$name}\n";
}
