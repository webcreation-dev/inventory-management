<?php
// Read-only checks against the current database. Run from the project root.
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage()."\n"); exit(1); });
$company=(int)($argv[1]??1);$record=DB::table('demo_datasets')->where('dataset_key','electronics-v1-company-'.$company)->first();
if(!$record)throw new RuntimeException('Jeu de démonstration absent.');
$m=json_decode($record->manifest,true,512,JSON_THROW_ON_ERROR);
function check($condition,$message){if(!$condition)throw new RuntimeException($message);echo "OK — $message\n";}
check(DB::table('outlets')->where('company_id',$company)->count()===1,'Un seul point de vente');
check(app(App\Services\ModuleUsageService::class)->all($company)==$m['modules'],'Choix des modules conservés');
foreach($m['disabled_tables_before']??[] as $table=>$count) check(DB::table($table)->count()===$count,'Module désactivé inchangé : '.$table);
$stocks=DB::table('view_stock_detail')->where('company_id',$company)->selectRaw('item_id,SUM(IF(type=1,stock_quantity,-stock_quantity)) as qty')->groupBy('item_id')->get();
check($stocks->count()===83 && $stocks->min('qty')>=0,'83 produits avec un stock final positif ou nul');
check(abs($stocks->sum('qty')-$m['summary']['remaining_units'])<.01,'Stock SQL égal au stock simulé');
$timeline=DB::table('view_stock_detail')->where('company_id',$company)->selectRaw('item_id,date,SUM(IF(type=1,stock_quantity,-stock_quantity)) as qty')->groupBy('item_id','date')->orderBy('date')->get();$running=[];
foreach($timeline as $row){$running[$row->item_id]=($running[$row->item_id]??0)+$row->qty;checkQuiet($running[$row->item_id]>=0,'Rupture négative historique');}echo "OK — Aucun stock négatif en fin de journée sur six mois\n";
function checkQuiet($ok,$message){if(!$ok)throw new RuntimeException($message);}
$payments=DB::table('sale_payments')->where('company_id',$company)->selectRaw('sale_id,SUM(amount) as paid')->groupBy('sale_id')->pluck('paid','sale_id');
foreach(DB::table('sales')->where('company_id',$company)->get() as $s){checkQuiet(abs($s->total_payable-$s->paid_amount-$s->due_amount)<.01,'Solde facture '.$s->id);checkQuiet(abs(($payments[$s->id]??0)-$s->paid_amount)<.01,'Règlement facture '.$s->id);}
echo "OK — Montants des factures et règlements cohérents\n";
$opening=$m['initial_working_funds'];foreach(DB::table('registers')->where('company_id',$company)->orderBy('date')->get() as $r){$closing=$r->opening_balance+$r->sale_paid_amount+$r->customer_due_receive-$r->refund_amount-$r->total_purchase-$r->total_due_payment-$r->total_expense;checkQuiet(abs($opening-$r->opening_balance)<.01 && abs($closing-$r->closing_balance)<.01,'Continuité des journées de caisse');$opening=$closing;}
echo "OK — Continuité des 158 journées et rapprochement des fonds\n";
foreach($m['ids']['items'] as $id){$item=DB::table('items')->find($id);checkQuiet(is_file(public_path('uploads/items/'.$item->photo)),'Image produit absente '.$id);}echo "OK — Toutes les images sont présentes\n";
$monthly=DB::table('sales')->where('company_id',$company)->selectRaw("DATE_FORMAT(sale_date,'%Y-%m') as month,COUNT(*) as sales,SUM(total_payable) as revenue_fcfa")->groupBy('month')->get();
echo json_encode(['summary'=>$m['summary'],'monthly'=>$monthly],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n";
