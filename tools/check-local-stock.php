<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!$app->environment('local')) throw new RuntimeException('Local environment required');
use Illuminate\Support\Facades\DB;
DB::beginTransaction();
try {
    $category=Modules\Stock\Models\ItemCategory::create(['name'=>'Verification temporaire','company_id'=>1,'user_id'=>1]);
    $unit=Modules\Stock\Models\Unit::create(['unit_name'=>'Piece','company_id'=>1,'user_id'=>1]);
    $item=Modules\Stock\Models\Item::create(['name'=>'Verification temporaire','code'=>'LOCAL-CHECK','type'=>'General_Product','category_id'=>$category->id,'sale_unit_id'=>$unit->id,'purchase_unit_id'=>$unit->id,'conversion_rate'=>1,'sale_price'=>1500,'purchase_price'=>1000,'company_id'=>1,'user_id'=>1,'del_status'=>'Live']);
    $saved=Modules\Stock\Models\Item::findOrFail($item->id);
    if ((float)$saved->sale_price!==1500.0 || $saved->category_id!=$category->id) throw new RuntimeException('Item persistence mismatch');
    echo "Category, unit and item persistence: OK (rolled back)\n";
} finally { DB::rollBack(); }
