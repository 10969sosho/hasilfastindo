<?php

$models = [
    'Branch' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ["code", "name", "address", "phone", "is_central"];
    protected $casts = ["is_central" => "boolean"];

    public function warehouses() { return $this->hasMany(Warehouse::class); }
    public function users() { return $this->hasMany(User::class); }
    public function stocks() { return $this->hasMany(Stock::class); }
    public function salesOrders() { return $this->hasMany(SalesOrder::class); }
}
',
    'Warehouse' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $fillable = ["branch_id", "code", "name", "description"];

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
    protected $fillable = ["warehouse_id", "bin_code", "area", "rack", "shelf", "bin", "description"];

    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function stocks() { return $this->hasMany(Stock::class); }
}
',
    'Uom' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Uom extends Model
{
    protected $fillable = ["code", "name"];
}
',
    'Category' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ["code", "name"];

    public function items() { return $this->hasMany(Item::class); }
}
',
    'Item' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $fillable = ["sku", "name", "category_id", "base_uom_id", "min_stock", "description"];

    public function category() { return $this->belongsTo(Category::class); }
    public function baseUom() { return $this->belongsTo(Uom::class, "base_uom_id"); }
    public function uomConversions() { return $this->hasMany(ItemUomConversion::class); }
    public function stocks() { return $this->hasMany(Stock::class); }
    public function batches() { return $this->hasMany(Batch::class); }

    public function convertToBaseUnits(float $qty, int $uomId): float
    {
        if ($uomId == $this->base_uom_id) return $qty;
        $conversion = $this->uomConversions()->where("from_uom_id", $uomId)->where("to_uom_id", $this->base_uom_id)->first();
        if ($conversion && $conversion->multiplier > 0) return $qty * $conversion->multiplier;
        return $qty;
    }
}
',
    'ItemUomConversion' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemUomConversion extends Model
{
    protected $fillable = ["item_id", "from_uom_id", "to_uom_id", "multiplier"];
    protected $casts = ["multiplier" => "decimal:4"];

    public function item() { return $this->belongsTo(Item::class); }
    public function fromUom() { return $this->belongsTo(Uom::class, "from_uom_id"); }
    public function toUom() { return $this->belongsTo(Uom::class, "to_uom_id"); }
}
',
    'Batch' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    protected $fillable = ["item_id", "batch_number", "cost_price", "expired_date", "notes"];
    protected $casts = ["expired_date" => "date", "cost_price" => "decimal:2"];

    public function item() { return $this->belongsTo(Item::class); }
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
        "uom_id", "qty", "barcode", "received_at"
    ];
    protected $casts = [
        "qty" => "decimal:3",
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
            ->where("qty", ">", 0)
            ->orderBy("received_at", "asc")
            ->get();
    }
}
',
    'StockMovement' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        "date", "branch_id", "warehouse_id", "location_id", "item_id", "batch_id",
        "type", "reference_no", "qty_in", "qty_out", "uom_id", "user_id", "notes"
    ];
    protected $casts = [
        "date" => "date",
        "qty_in" => "decimal:3",
        "qty_out" => "decimal:3",
    ];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function uom() { return $this->belongsTo(Uom::class); }
    public function user() { return $this->belongsTo(User::class); }
}
',
    'Supplier' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = ["code", "name", "phone", "email", "address"];
}
',
    'Customer' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ["code", "name", "phone", "email", "address"];
    public function salesOrders() { return $this->hasMany(SalesOrder::class); }
}
',
    'Vehicle' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = ["plate_number", "name", "type", "driver_id", "helper_id", "status"];

    public function driver() { return $this->belongsTo(Driver::class, "driver_id"); }
    public function helper() { return $this->belongsTo(Driver::class, "helper_id"); }
    public function deliveries() { return $this->hasMany(Delivery::class); }
}
',
    'Driver' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = ["name", "phone", "license_no", "role", "is_active"];
    protected $casts = ["is_active" => "boolean"];

    public function deliveriesAsDriver() { return $this->hasMany(Delivery::class, "driver_id"); }
    public function deliveriesAsHelper() { return $this->hasMany(Delivery::class, "helper_id"); }
}
',
    'GoodsReceipt' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    protected $fillable = [
        "receipt_no", "supplier_id", "branch_id", "warehouse_id", "location_id",
        "doc_number", "source_so", "received_at", "status", "notes", "user_id"
    ];
    protected $casts = ["received_at" => "datetime"];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(GoodsReceiptItem::class); }
}
',
    'GoodsReceiptItem' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    protected $fillable = [
        "goods_receipt_id", "item_id", "batch_id", "batch_number", "uom_id",
        "qty_uom", "multiplier", "qty_pcs", "location_id", "barcode", "notes"
    ];
    protected $casts = [
        "qty_uom" => "decimal:3",
        "multiplier" => "decimal:4",
        "qty_pcs" => "decimal:3",
    ];

    public function goodsReceipt() { return $this->belongsTo(GoodsReceipt::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function uom() { return $this->belongsTo(Uom::class); }
    public function location() { return $this->belongsTo(Location::class); }
}
',
    'SalesOrder' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrder extends Model
{
    protected $fillable = [
        "so_number", "customer_id", "branch_id", "fulfillment_branch_id",
        "order_date", "status", "notes", "user_id"
    ];
    protected $casts = ["order_date" => "date"];

    public function customer() { return $this->belongsTo(Customer::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function fulfillmentBranch() { return $this->belongsTo(Branch::class, "fulfillment_branch_id"); }
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(SalesOrderItem::class); }
    public function packingLists() { return $this->hasMany(PackingList::class); }
}
',
    'SalesOrderItem' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesOrderItem extends Model
{
    protected $fillable = [
        "sales_order_id", "item_id", "requested_qty", "fulfilled_qty", "uom_id", "notes"
    ];
    protected $casts = [
        "requested_qty" => "decimal:3",
        "fulfilled_qty" => "decimal:3",
    ];

    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function uom() { return $this->belongsTo(Uom::class); }
}
',
    'StockTransfer' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    protected $fillable = [
        "transfer_no", "from_branch_id", "to_branch_id", "transfer_date",
        "received_date", "status", "created_by", "received_by", "notes", "discrepancy_notes"
    ];
    protected $casts = [
        "transfer_date" => "date",
        "received_date" => "date",
    ];

    public function fromBranch() { return $this->belongsTo(Branch::class, "from_branch_id"); }
    public function toBranch() { return $this->belongsTo(Branch::class, "to_branch_id"); }
    public function creator() { return $this->belongsTo(User::class, "created_by"); }
    public function receiver() { return $this->belongsTo(User::class, "received_by"); }
    public function items() { return $this->hasMany(StockTransferItem::class); }
}
',
    'StockTransferItem' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransferItem extends Model
{
    protected $fillable = [
        "stock_transfer_id", "item_id", "batch_id", "from_location_id",
        "to_location_id", "qty", "received_qty", "uom_id", "barcode", "notes"
    ];
    protected $casts = [
        "qty" => "decimal:3",
        "received_qty" => "decimal:3",
    ];

    public function stockTransfer() { return $this->belongsTo(StockTransfer::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function fromLocation() { return $this->belongsTo(Location::class, "from_location_id"); }
    public function toLocation() { return $this->belongsTo(Location::class, "to_location_id"); }
    public function uom() { return $this->belongsTo(Uom::class); }
}
',
    'StockRepack' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockRepack extends Model
{
    protected $fillable = [
        "repack_no", "branch_id", "repack_date", "source_item_id", "source_batch_id",
        "source_location_id", "source_barcode", "source_qty", "source_uom_id",
        "target_item_id", "target_batch_id", "target_location_id", "target_barcode",
        "target_qty", "target_uom_id", "notes", "user_id"
    ];
    protected $casts = [
        "repack_date" => "date",
        "source_qty" => "decimal:3",
        "target_qty" => "decimal:3",
    ];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function sourceItem() { return $this->belongsTo(Item::class, "source_item_id"); }
    public function targetItem() { return $this->belongsTo(Item::class, "target_item_id"); }
    public function sourceUom() { return $this->belongsTo(Uom::class, "source_uom_id"); }
    public function targetUom() { return $this->belongsTo(Uom::class, "target_uom_id"); }
    public function user() { return $this->belongsTo(User::class); }
}
',
    'PackingList' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingList extends Model
{
    protected $fillable = [
        "packing_no", "sales_order_id", "branch_id", "customer_name",
        "total_boxes", "packed_by", "packed_at", "status", "notes"
    ];
    protected $casts = [
        "packed_at" => "datetime",
        "total_boxes" => "integer",
    ];

    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function packer() { return $this->belongsTo(User::class, "packed_by"); }
    public function boxes() { return $this->hasMany(PackingListBox::class); }
    public function items() { return $this->hasMany(PackingListItem::class); }
}
',
    'PackingListBox' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingListBox extends Model
{
    protected $fillable = ["packing_list_id", "box_number", "box_barcode", "notes"];

    public function packingList() { return $this->belongsTo(PackingList::class); }
    public function items() { return $this->hasMany(PackingListItem::class); }
}
',
    'PackingListItem' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackingListItem extends Model
{
    protected $fillable = [
        "packing_list_id", "packing_list_box_id", "item_id", "batch_id", "qty", "uom_id", "barcode"
    ];
    protected $casts = ["qty" => "decimal:3"];

    public function packingList() { return $this->belongsTo(PackingList::class); }
    public function box() { return $this->belongsTo(PackingListBox::class, "packing_list_box_id"); }
    public function item() { return $this->belongsTo(Item::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function uom() { return $this->belongsTo(Uom::class); }
}
',
    'Delivery' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        "delivery_no", "sales_order_id", "packing_list_id", "branch_id",
        "vehicle_id", "driver_id", "helper_id", "driver_name", "helper_name",
        "destination_address", "method", "departure_time", "arrival_time",
        "status", "received_by", "received_notes", "received_photo", "user_id", "notes"
    ];
    protected $casts = [
        "departure_time" => "datetime",
        "arrival_time" => "datetime",
    ];

    public function salesOrder() { return $this->belongsTo(SalesOrder::class); }
    public function packingList() { return $this->belongsTo(PackingList::class); }
    public function branch() { return $this->belongsTo(Branch::class); }
    public function vehicle() { return $this->belongsTo(Vehicle::class); }
    public function driver() { return $this->belongsTo(Driver::class, "driver_id"); }
    public function helper() { return $this->belongsTo(Driver::class, "helper_id"); }
    public function user() { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(DeliveryItem::class); }
    public function tracks() { return $this->hasMany(DeliveryTrack::class); }
}
',
    'DeliveryItem' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryItem extends Model
{
    protected $fillable = [
        "delivery_id", "packing_list_box_id", "item_id", "batch_id", "qty", "uom_id", "barcode", "scanned_out_at", "status"
    ];
    protected $casts = [
        "qty" => "decimal:3",
        "scanned_out_at" => "datetime",
    ];

    public function delivery() { return $this->belongsTo(Delivery::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function box() { return $this->belongsTo(PackingListBox::class, "packing_list_box_id"); }
}
',
    'DeliveryTrack' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryTrack extends Model
{
    protected $fillable = ["delivery_id", "status", "description", "photo", "user_id"];

    public function delivery() { return $this->belongsTo(Delivery::class); }
    public function user() { return $this->belongsTo(User::class); }
}
',
    'StockOpname' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpname extends Model
{
    protected $fillable = [
        "opname_no", "branch_id", "warehouse_id", "location_id", "title",
        "opname_date", "status", "created_by", "approved_by", "notes"
    ];
    protected $casts = ["opname_date" => "date"];

    public function branch() { return $this->belongsTo(Branch::class); }
    public function warehouse() { return $this->belongsTo(Warehouse::class); }
    public function location() { return $this->belongsTo(Location::class); }
    public function creator() { return $this->belongsTo(User::class, "created_by"); }
    public function approver() { return $this->belongsTo(User::class, "approved_by"); }
    public function items() { return $this->hasMany(StockOpnameItem::class); }
}
',
    'StockOpnameItem' => '<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockOpnameItem extends Model
{
    protected $fillable = [
        "stock_opname_id", "item_id", "batch_id", "uom_id", "scanned_barcode",
        "system_qty", "physical_qty", "difference_qty", "status", "notes"
    ];
    protected $casts = [
        "system_qty" => "decimal:3",
        "physical_qty" => "decimal:3",
        "difference_qty" => "decimal:3",
    ];

    public function stockOpname() { return $this->belongsTo(StockOpname::class); }
    public function item() { return $this->belongsTo(Item::class); }
    public function batch() { return $this->belongsTo(Batch::class); }
    public function uom() { return $this->belongsTo(Uom::class); }
}
',
];

foreach ($models as $name => $content) {
    file_put_contents("app/Models/{$name}.php", $content);
    echo "Created Model: {$name}\n";
}
