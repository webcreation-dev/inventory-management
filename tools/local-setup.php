<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!$app->environment('local')) throw new RuntimeException('Local environment required');
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
$definitions=json_decode(file_get_contents(database_path('local-recovery-schema.json')),true);
foreach ($definitions as $name=>$columns) {
 if (!Schema::hasTable($name)) Schema::create($name,function(Blueprint $t){$t->id();$t->timestamps();});
 foreach ($columns as $column) {
  if (!Schema::hasColumn($name,$column)) Schema::table($name,function(Blueprint $t)use($column){
   if ($column==='del_status') $t->string($column,20)->default('Live');
   elseif ($column==='active_status') $t->string($column,20)->default('Active');
   elseif (preg_match('/^(amount|total|quantity|balance|paid|due|opening_balance)$/',$column)) $t->decimal($column,20,6)->nullable()->default(0);
   else $t->longText($column)->nullable();
  });
 }

 if (!Schema::hasColumn($name,'del_status')) Schema::table($name,fn(Blueprint $t)=>$t->string('del_status',20)->default('Live'));
}
if (!DB::table('companies')->exists()) (new Database\Seeders\ConfigurationSeeder)->run();
DB::table('companies')->where('id',1)->update(['zone_name'=>'Africa/Porto-Novo','currency'=>'FCFA','business_name'=>'Ma boutique']);
DB::table('users')->where('email','admin@doorsoft.co')->update(['outlet_id'=>'1','active_status'=>'Active','will_login'=>'Yes']);
echo "Local setup ready\n";
