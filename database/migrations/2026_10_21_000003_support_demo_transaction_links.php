<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $columns = [
            'sale_return_details' => ['item_id'=>'id','outlet_id'=>'id','item_type'=>'text','expiry_imei_serial'=>'text'],
            'supplier_payments' => ['outlet_id'=>'id'],
            'expenses' => ['outlet_id'=>'id'],
            'set_opening_stocks' => ['expiry_imei_serial'=>'text'],
            'transfer_details' => ['quantity_amount'=>'number','item_type'=>'text','expiry_imei_serial'=>'text'],
            'damage_details' => ['item_type'=>'text','expiry_imei_serial'=>'text'],
        ];
        foreach ($columns as $table => $fields) {
            foreach ($fields as $name => $type) {
                if (!Schema::hasColumn($table, $name)) Schema::table($table, function (Blueprint $t) use ($name, $type) {
                    if ($type === 'id') $t->unsignedBigInteger($name)->nullable()->index();
                    elseif ($type === 'number') $t->decimal($name,20,6)->nullable();
                    else $t->string($name,255)->nullable();
                });
            }
        }
        if (!Schema::hasTable('demo_datasets')) Schema::create('demo_datasets', function (Blueprint $t) {
            $t->id();$t->string('dataset_key')->unique();$t->json('manifest');$t->timestamps();
        });
        // The original installer normally supplies this view with its SQL dump.
        // Do not replace an existing vendor view.
        if (!Schema::hasTable('view_stock_detail')) {
            $parts = [
                "SELECT d.id,d.item_id,d.item_type,d.outlet_id,d.company_id,1 AS type,CAST(d.stock_quantity AS DECIMAL(20,6)) AS stock_quantity,d.expiry_imei_serial,DATE(d.created_at) AS date,'opening' AS source FROM set_opening_stocks d WHERE d.del_status='Live'",
                "SELECT d.id,d.item_id,d.item_type,d.outlet_id,d.company_id,1,CAST(d.quantity_amount AS DECIMAL(20,6)),d.expiry_imei_serial,p.date,'purchase' FROM purchase_details d JOIN purchases p ON p.id=d.purchase_id WHERE d.del_status='Live' AND p.del_status='Live'",
                "SELECT d.id,d.item_id,d.item_type,d.outlet_id,d.company_id,2,CAST(d.qty AS DECIMAL(20,6)),d.expiry_imei_serial,s.sale_date,'sale' FROM sale_details d JOIN sales s ON s.id=d.sales_id WHERE d.del_status='Live' AND s.del_status='Live'",
                "SELECT d.id,d.item_id,d.item_type,d.outlet_id,d.company_id,1,CAST(d.return_quantity_amount AS DECIMAL(20,6)),d.expiry_imei_serial,r.date,'sale_return' FROM sale_return_details d JOIN sale_returns r ON r.id=d.sale_return_id WHERE d.del_status='Live' AND r.del_status='Live'",
                "SELECT d.id,d.item_id,d.item_type,h.outlet_id,d.company_id,2,CAST(d.damage_quantity AS DECIMAL(20,6)),d.expiry_imei_serial,h.date,'damage' FROM damage_details d JOIN damages h ON h.id=d.damage_id WHERE d.del_status='Live' AND h.del_status='Live'",
                "SELECT d.id,d.item_id,d.item_type,h.from_outlet_id,d.company_id,2,CAST(d.quantity_amount AS DECIMAL(20,6)),d.expiry_imei_serial,h.date,'transfer_out' FROM transfer_details d JOIN transfers h ON h.id=d.transfer_id WHERE d.del_status='Live' AND h.del_status='Live'",
                "SELECT d.id,d.item_id,d.item_type,h.to_outlet_id,d.company_id,1,CAST(d.quantity_amount AS DECIMAL(20,6)),d.expiry_imei_serial,h.date,'transfer_in' FROM transfer_details d JOIN transfers h ON h.id=d.transfer_id WHERE d.del_status='Live' AND h.del_status='Live'",
            ];
            DB::statement('CREATE VIEW view_stock_detail AS '.implode(' UNION ALL ', $parts));
        }
    }
    public function down(): void { /* Preserve transactional data and the stock view. */ }
};
