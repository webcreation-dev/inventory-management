<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\ModuleUsageService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use RuntimeException;

class ElectronicsDemoSeeder extends Seeder
{
    public int $companyId = 1;
    public ?string $endDate = null;
    private int $outletId;
    private int $adminId;
    private array $modules;
    private array $manifest = [];
    private array $catalog = [];
    private array $customers = [];
    private array $employees = [];
    private array $stock = [];
    private array $pending = [];
    private int $cashId;
    private int $bankId;
    private int $counterId;
    private int $walkInId;
    private array $expenseCategories = [];
    private array $columns = [];
    private CarbonImmutable $end;
    private float $funds = 50000000;
    private array $dayTotals = [];

    private function enabled(string $key): bool { return (bool)($this->modules[$key] ?? false); }
    private function row(string $table, array $data, string $stamp): int
    {
        $values = array_merge(['company_id'=>$this->companyId,'user_id'=>$this->adminId,'outlet_id'=>$this->outletId,'del_status'=>'Live','created_at'=>$stamp,'updated_at'=>$stamp], $data);
        $this->columns[$table] ??= Schema::getColumnListing($table);
        // outlet_id is not applicable to shared catalogue/contacts/settings tables.
        if (!in_array('outlet_id',$this->columns[$table],true)) unset($values['outlet_id']);
        $missing = array_diff(array_keys($values), $this->columns[$table]);
        if ($missing) throw new RuntimeException($table.' : colonnes absentes : '.implode(', ',$missing));
        $id = DB::table($table)->insertGetId($values);
        $this->manifest['ids'][$table][] = $id;
        return $id;
    }

    public function run(): void
    {
        $key = 'electronics-v1-company-'.$this->companyId;
        if ($record = DB::table('demo_datasets')->where('dataset_key',$key)->first()) {
            $this->command?->info('Ce jeu de démonstration existe déjà. Aucune donnée ajoutée.');
            return;
        }
        $this->modules = app(ModuleUsageService::class)->all($this->companyId);
        foreach (['menu_item_configuration','menu_items','menu_stock','menu_sales','menu_purchases','menu_customers','menu_suppliers'] as $required) {
            if (!$this->enabled($required)) throw new RuntimeException('Le scénario nécessite le module actif '.$required.'.');
        }
        $outlets = DB::table('outlets')->where('company_id',$this->companyId)->get();
        if ($outlets->count() !== 1) throw new RuntimeException('Le scénario nécessite exactement un point de vente existant.');
        $this->outletId = (int)$outlets->first()->id;
        $this->adminId = (int)User::where('company_id',$this->companyId)->where('role',1)->value('id');
        if (!$this->adminId) throw new RuntimeException('Administrateur de l’entreprise introuvable.');
        foreach (['items','sales','purchases','expenses','registers'] as $table) {
            if (DB::table($table)->where('company_id',$this->companyId)->exists()) throw new RuntimeException('Des données existent dans '.$table.'. Le scénario refuse de les mélanger ou de les effacer.');
        }
        $this->cashId = (int)DB::table('payment_methods')->where('company_id',$this->companyId)->where('account_type','Cash')->value('id');
        $this->bankId = (int)DB::table('payment_methods')->where('company_id',$this->companyId)->where('account_type','Bank_Account')->value('id');
        if (!$this->cashId || !$this->bankId) throw new RuntimeException('Les moyens de paiement techniques Espèces et Banque sont nécessaires aux ventes.');
        if ($this->endDate && CarbonImmutable::parse($this->endDate,'Africa/Porto-Novo')->isFuture()) throw new RuntimeException('La date de fin ne peut pas être future.');
        $this->end = $this->endDate ? CarbonImmutable::parse($this->endDate,'Africa/Porto-Novo')->endOfDay() : CarbonImmutable::now('Africa/Porto-Novo');
        $start = $this->end->subMonthsNoOverflow(6)->startOfDay();
        $this->manifest = ['key'=>$key,'fictional'=>true,'from'=>$start->toDateString(),'to'=>$this->end->toDateString(),'modules'=>$this->modules,'ids'=>[],'days'=>[],'initial_working_funds'=>$this->funds,'previous_company'=>(array)DB::table('companies')->find($this->companyId),'previous_outlet'=>(array)$outlets->first()];
        $disabled = $this->disabledCounts();
        $this->manifest['disabled_tables_before'] = $disabled;
        mt_srand(20261021);
        DB::transaction(function () use ($start, $key, $disabled) {
            $this->setup($start);
            for ($day=$start; $day->lte($this->end); $day=$day->addDay()) {
                if ($day->isSunday()) continue;
                $this->simulateDay($day, $day->equalTo($start));
            }
            if ($this->disabledCounts() !== $disabled) throw new RuntimeException('Une table de module désactivé a été modifiée.');
            if (DB::table('outlets')->where('company_id',$this->companyId)->count() !== 1) throw new RuntimeException('Le point de vente doit rester unique.');
            $this->manifest['summary'] = $this->summary();
            DB::table('demo_datasets')->insert(['dataset_key'=>$key,'manifest'=>json_encode($this->manifest,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now()]);
        });
        $path=storage_path('app/demo-electronics-report.json');
        file_put_contents($path,json_encode($this->manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $this->command?->info(json_encode($this->manifest['summary'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }

    private function disabledCounts(): array
    {
        $mapping=['menu_income'=>['incomes'],'menu_purchase_returns'=>['purchase_returns','purchase_return_details'],'menu_transfers'=>['transfers','transfer_details'],'menu_damage'=>['damages','damage_details'],'menu_quotations'=>['quotations','quotation_details'],'fixed_assets'=>['fixed_asset_items','fixed_asset_stock_ins','fixed_asset_stock_outs'],'menu_payment_accounts'=>['payment_methods'],'menu_accounting'=>['deposit_withdraws'],'menu_attendance'=>['attendances'],'menu_payroll'=>['salaries','salary_items','salary_payments'],'menu_advances'=>['employee_advance_payments'],'menu_ecommerce'=>['ecommerce_orders'],'booking'=>['bookings'],'warranty'=>['warranties','servicings'],'installment_sale'=>['installment_sales']];
        $counts=[];
        foreach ($mapping as $module=>$tables) if (!$this->enabled($module)) foreach ($tables as $table) {
            if (Schema::hasTable($table)) $counts[$table]=DB::table($table)->count();
        }
        return $counts;
    }

    private function setup(CarbonImmutable $start): void
    {
        $stamp=$start->setTime(8,0)->toDateTimeString();
        DB::table('companies')->where('id',$this->companyId)->update(['business_name'=>'Tech Horizon — Démonstration','short_name'=>'Tech Horizon','currency'=>'FCFA','currency_position'=>'After Amount','precision'=>0,'zone_name'=>'Africa/Porto-Novo','date_format'=>'d/m/Y','collect_tax'=>'No']);
        DB::table('outlets')->where('id',$this->outletId)->update(['outlet_name'=>'Tech Horizon — Cotonou','outlet_code'=>'TH-001','address'=>'Boutique de démonstration — quartier Akpakpa, Cotonou','phone'=>'+229 01 00 00 10 01','email'=>'boutique@tech-horizon.example.invalid','active_status'=>'Active']);
        $this->counterId=$this->row('counters',['name'=>'Caisse principale','description'=>'Comptoir unique — démonstration','status'=>'Active'],$stamp);
        $this->walkInId=(int)DB::table('customers')->where('company_id',$this->companyId)->where('name','Walk-in Customer')->value('id');
        if (!$this->walkInId) $this->walkInId=$this->row('customers',['name'=>'Walk-in Customer','is_installment_customer'=>'No','opening_balance'=>0,'opening_balance_type'=>'Debit','credit_limit'=>0],$stamp);
        if ($this->enabled('menu_employees')) {
            foreach (['Aïcha Dossou — démo','Kevin Houngbédji — démo','Grâce Soglo — démo'] as $i=>$name) {
                $user=User::create(['name'=>$name,'email'=>'employe'.($i+1).'@tech-horizon.example.invalid','phone'=>'+22901000011'.sprintf('%02d',$i+1),'password'=>Hash::make(bin2hex(random_bytes(20))),'company_id'=>$this->companyId,'outlet_id'=>(string)$this->outletId,'active_status'=>'Inactive','will_login'=>'No','language'=>'fr','del_status'=>'Live','role'=>2]);
                $this->employees[]=$user->id;$this->manifest['ids']['users'][]=$user->id;
            }
        }
        if (!$this->employees) $this->employees=[$this->adminId];
        if ($this->enabled('menu_roles')) {
            foreach (['Vendeur — démo'=>['sale-list','sale-create','customer-list','customer-create','item-list','stock-list','dashboard-dashboard'],'Gestionnaire stock — démo'=>['item-list','item-create','item-edit','purchase-list','purchase-create','supplier-list','supplier-create','stock-list']] as $name=>$permissions) {
                $role=Role::create(['name'=>$name,'guard_name'=>'web']);$this->manifest['ids']['roles'][]=$role->id;
                $role->syncPermissions(Permission::whereIn('name',$permissions)->get());
                foreach ($this->employees as $i=>$id) if ($id!==$this->adminId && (($i<2 && str_starts_with($name,'Vendeur')) || ($i===2 && str_starts_with($name,'Gestionnaire')))) {
                    $user=User::findOrFail($id);$user->assignRole($role);$user->update(['role'=>$role->id]);
                }
            }
        }
        $first=['Aïcha','Jean','Mariam','Luc','Grâce','Alain','Fatou','David','Nadia','Serge','Estelle','Ibrahim','Carine','Patrick','Rosine','Daniel','Aminata','Brice','Sandra','Franck'];
        $last=['Dossou','Hounkpè','Soglo','Adjovi','Houngbédji','Agossou','Kpadonou','Adé','Hounnou','Zinsou','Kora','Amoussou'];
        $districts=['Akpakpa','Cadjèhoun','Godomey','Fidjrossè','Agla','Ganhi'];
        for ($i=0;$i<64;$i++) {
            $name=$i<54 ? $first[$i%count($first)].' '.$last[intdiv($i,count($first))%count($last)] : ['Atelier Baobab','Cabinet Horizon','Centre Alpha','École des Hibiscus','Studio Lumière','Bureau Kora','Agence Azur','Maison Sésame','Association Espoir','Services Atlantique'][$i-54].' — démo';
            $this->customers[]=$this->row('customers',['name'=>$name,'phone'=>'+229010000'.sprintf('%04d',3000+$i),'email'=>'client'.($i+1).'@tech-horizon.example.invalid','address'=>$districts[$i%6].', Cotonou — adresse fictive','is_installment_customer'=>'No','opening_balance'=>0,'opening_balance_type'=>'Debit','balance_type'=>'Debit','credit_limit'=>$i>=54 ? 1000000 : 300000,'customer_type'=>'Regular','date_of_birth'=>'1990-'.sprintf('%02d',1+$i%12).'-'.sprintf('%02d',1+$i%27)],$stamp);
        }
        $suppliers=[];
        foreach (['Atlantique Informatique','Distribution Bénin Tech','Comptoir Mobile','Accessoires Horizon','Réseau Pro Distribution','Pièces & Stockage','Bureautique Centrale','Import Numérique'] as $i=>$name) {
            $suppliers[]=$this->row('suppliers',['name'=>$name.' — démo','contact_person'=>'Contact fournisseur '.($i+1),'phone'=>'+229010000'.sprintf('%04d',2000+$i),'email'=>'fournisseur'.($i+1).'@tech-horizon.example.invalid','address'=>'Zone commerciale fictive — Cotonou','description'=>'Fournisseur fictif du jeu de démonstration','opening_balance'=>0,'opening_balance_type'=>'Credit','balance_type'=>'Credit'],$stamp);
        }
        $unit=$this->row('units',['unit_name'=>'Pièce','description'=>'Vente à l’unité'],$stamp);
        $brands=[];$categories=[];$subs=[];
        $images=json_decode(file_get_contents(database_path('demo/image-attributions.json')),true,512,JSON_THROW_ON_ERROR);
        $rows=json_decode(file_get_contents(database_path('demo/electronics-catalog.json')),true,512,JSON_THROW_ON_ERROR);
        if (!is_dir(public_path('uploads/items'))) mkdir(public_path('uploads/items'),0775,true);
        foreach ($rows as $index=>$item) {
            $category=$item['category'];$brand=$item['brand'];$subcategory=$category.'|'.$item['subcategory'];
            $categories[$category] ??= $this->row('item_categories',['name'=>$category,'description'=>'Catalogue informatique et téléphonie — démonstration','show_in_header'=>0,'sort_id'=>count($categories)+1],$stamp);
            $brands[$brand] ??= $this->row('brands',['name'=>$brand,'description'=>'Marque du catalogue de démonstration'],$stamp);
            $subs[$subcategory] ??= $this->row('item_sub_categories',['name'=>$item['subcategory'],'category_id'=>$categories[$category],'description'=>'Sous-catégorie de démonstration','sort_id'=>count($subs)+1],$stamp);
            $image=$images[$item['image']] ?? null;
            if (!$image || !is_file(public_path('demo-assets/electronics/'.$image['file']))) throw new RuntimeException('Illustration absente : '.$item['image']);
            $photo='demo-electronics-'.$image['file'];copy(public_path('demo-assets/electronics/'.$image['file']),public_path('uploads/items/'.$photo));
            $supplier=$suppliers[$index%count($suppliers)];
            $id=$this->row('items',['name'=>$brand.' '.$item['name'],'code'=>$item['code'],'alternative_name'=>$item['subcategory'],'type'=>'General_Product','category_id'=>$categories[$category],'sub_category_id'=>$subs[$subcategory],'brand_id'=>$brands[$brand],'supplier_id'=>$supplier,'purchase_unit_id'=>$unit,'sale_unit_id'=>$unit,'unit_type'=>1,'conversion_rate'=>1,'purchase_price'=>$item['cost'],'last_purchase_price'=>$item['cost'],'last_three_purchase_avg'=>$item['cost'],'sale_price'=>$item['price'],'whole_sale_price'=>round($item['price']*.94/100)*100,'profit_margin'=>round(($item['price']-$item['cost'])/$item['cost']*100,2),'alert_quantity'=>$item['price']>=90000?3:12,'description'=>$item['description'].' Illustration : '.$image['author'].' — '.$image['license'].' — '.$image['source'].' — '.$image['license_url'],'photo'=>$photo,'parent_id'=>0,'enable_disable_status'=>'Enable','tax_type'=>'Exclusive','tax_information'=>'[]','applicable_tax_id'=>'[]','tax_string'=>'','loyalty_point'=>0,'expiry_date_maintain'=>'No'],$stamp);
            $this->catalog[$id]=array_merge($item,['id'=>$id,'supplier'=>$supplier,'initial'=>$item['price']>=90000?mt_rand(2,3):mt_rand(8,16)]);$this->stock[$id]=0;
        }
        if ($this->enabled('menu_expenses')) foreach (['Loyer boutique','Électricité et eau','Internet et téléphone','Transport et livraison','Fournitures et entretien'] as $name) $this->expenseCategories[]=$this->row('expense_categories',['name'=>$name,'description'=>'Charges de fonctionnement — démonstration'],$stamp);
        if ($this->enabled('denomination')) foreach ([25,50,100,200,500,1000,2000,5000,10000] as $amount) $this->row('denominations',['amount'=>$amount,'description'=>'FCFA — démonstration'],$stamp);
        if ($this->enabled('multiple_currency')) $this->row('multiple_currencies',['currency'=>'EUR','conversion_rate'=>655.957],$stamp);
        if ($this->enabled('menu_sales')) {
            foreach (['Coursier boutique','Livraison partenaire'] as $i=>$name) $this->row('delivery_partners',['name'=>$name.' — démo','description'=>'Livraison de proximité — démonstration'],$stamp);
            $id=array_key_first($this->catalog);
            $this->row('promotions',['type'=>1,'title'=>'Rentrée informatique — démo','item_id'=>$id,'discount'=>'5%','start_date'=>$start->addMonths(4)->toDateString(),'end_date'=>$start->addMonths(5)->toDateString(),'status'=>2],$stamp);
        }
    }

    private function purchase(array $quantities, CarbonImmutable $day): void
    {
        $groups=[];
        foreach ($quantities as $id=>$qty) if ($qty>0) $groups[$this->catalog[$id]['supplier']][$id]=$qty;
        foreach ($groups as $supplier=>$lines) {
            $total=0;foreach ($lines as $id=>$qty) $total+=$this->catalog[$id]['cost']*$qty;
            $paid=mt_rand(1,100)<=30 ? round($total*.7) : $total;
            $stamp=$day->setTime(9,0)->toDateTimeString();
            $number='DEMO-ACH-'.$day->format('ymd').'-'.(count($this->manifest['ids']['purchases']??[])+1);
            $purchase=$this->row('purchases',['reference_no'=>$number,'invoice_no'=>$number,'supplier_id'=>$supplier,'date'=>$day->toDateString(),'grand_total'=>$total,'paid'=>$paid,'due_amount'=>$total-$paid,'discount'=>0,'other'=>0,'note'=>'Approvisionnement fictif du catalogue informatique'],$stamp);
            foreach ($lines as $id=>$qty) {
                $this->row('purchase_details',['purchase_id'=>$purchase,'item_id'=>$id,'item_type'=>'General_Product','quantity_amount'=>$qty,'unit_price'=>$this->catalog[$id]['cost'],'total'=>$this->catalog[$id]['cost']*$qty,'expiry_imei_serial'=>null],$stamp);
                $this->stock[$id]+=$qty;
            }
            $this->row('purchase_payments',['purchase_id'=>$purchase,'payment_id'=>$this->bankId,'amount'=>$paid,'date'=>$day->toDateString()],$stamp);
            $this->dayTotals['purchases']+=$paid;
            if ($total>$paid) $this->schedule($day->addDays(mt_rand(14,25)),['kind'=>'supplier','supplier_id'=>$supplier,'amount'=>$total-$paid,'reference'=>$number]);
        }
    }

    private function schedule(CarbonImmutable $date, array $event): void
    {
        if ($date->isSunday()) $date=$date->addDay();
        if ($date->lte($this->end)) $this->pending[$date->toDateString()][]=$event;
    }

    private function simulateDay(CarbonImmutable $day, bool $first): void
    {
        $this->dayTotals=['sales'=>0,'returns'=>0,'customer'=>0,'purchases'=>0,'supplier'=>0,'expenses'=>0];
        $opening=$this->funds;
        $replenish=[];
        foreach ($this->catalog as $id=>$item) if ($first || ($day->isMonday() && $this->stock[$id]<($item['price']>=90000?3:14))) $replenish[$id]=$item['initial'];
        if ($replenish) $this->purchase($replenish,$day);
        foreach ($this->pending[$day->toDateString()]??[] as $event) $this->settle($event,$day);
        $season = in_array($day->month,[8,9]) ? 3 : 0;
        $count=mt_rand(5,10)+$season+($day->isSaturday()?3:0);
        if ($day->isSameDay($this->end)) $count=min($count,max(3,$this->end->hour-9));
        $cheap=array_keys(array_filter($this->catalog,fn($i)=>$i['price']<65000));
        $premium=array_keys(array_filter($this->catalog,fn($i)=>$i['price']>=65000));
        for ($i=0;$i<$count;$i++) {
            $pool=mt_rand(1,100)<=65?$cheap:$premium;
            $itemId=$pool[array_rand($pool)];$lines=[$itemId=>$this->catalog[$itemId]['price']<15000?mt_rand(1,3):1];
            if ($this->catalog[$itemId]['price']>=65000 && mt_rand(1,100)<=65) {$accessory=$cheap[array_rand($cheap)];$lines[$accessory]=1;}
            $needed=[];foreach($lines as $id=>$qty) if($this->stock[$id]<$qty) $needed[$id]=$this->catalog[$id]['initial'];
            if($needed) $this->purchase($needed,$day);
            $hour=10+intdiv($i*8,max(1,$count));$minute=mt_rand(0,55);
            if($day->isSameDay($this->end) && $hour>=$this->end->hour){$hour=max(9,$this->end->hour-1);$minute=mt_rand(0,55);}
            $this->sale($lines,$day->setTime($hour,$minute),$day);
        }
        if ($this->enabled('menu_expenses')) {
            if ($day->day===1 || ($day->isSameDay($day->startOfMonth()->addDay()) && $day->startOfMonth()->isSunday())) $this->expense(0,150000,$day,'Loyer mensuel de la boutique');
            if ($day->day===6) $this->expense(1,mt_rand(30000,45000),$day,'Facture mensuelle d’électricité');
            if ($day->day===10) $this->expense(2,25000,$day,'Forfait Internet et téléphone');
            if ($day->isTuesday() || $day->isFriday()) $this->expense(3,mt_rand(3,9)*1000,$day,'Transport d’approvisionnement et courses');
            if ($day->day===18) $this->expense(4,mt_rand(9,18)*1000,$day,'Consommables et entretien boutique');
        }
        $this->funds=$opening+$this->dayTotals['sales']+$this->dayTotals['customer']-$this->dayTotals['returns']-$this->dayTotals['purchases']-$this->dayTotals['supplier']-$this->dayTotals['expenses'];
        if ($this->funds<0) throw new RuntimeException('Fonds de roulement négatifs au '.$day->toDateString());
        $paymentTotals=DB::table('sale_payments as p')->join('payment_methods as m','m.id','=','p.payment_id')->where('p.company_id',$this->companyId)->where('p.date',$day->toDateString())->selectRaw('m.name,SUM(p.amount) as total')->groupBy('m.name')->pluck('total','name')->all();
        $open=$day->setTime(8,30)->toDateTimeString();$closed=($day->isSameDay($this->end) ? $this->end->min($day->setTime(19,0)) : $day->setTime(19,0))->toDateTimeString();
        $this->row('registers',['counter_id'=>$this->counterId,'date'=>$day->toDateString(),'opening_balance'=>$opening,'closing_balance'=>$this->funds,'opening_balance_date_time'=>$open,'closing_balance_date_time'=>$day->isSameDay($this->end)?null:$closed,'opening_details'=>'Fonds de roulement simulés, espèces et banque réunies. Démonstration.','register_status'=>$day->isSameDay($this->end)?1:2,'sale_paid_amount'=>$this->dayTotals['sales'],'refund_amount'=>$this->dayTotals['returns'],'customer_due_receive'=>$this->dayTotals['customer'],'total_purchase'=>$this->dayTotals['purchases'],'total_due_payment'=>$this->dayTotals['supplier'],'total_expense'=>$this->dayTotals['expenses'],'total_purchase_return'=>0,'total_downpayment'=>0,'total_installmentcollection'=>0,'total_servicing'=>0,'payment_methods_sale'=>json_encode($paymentTotals),'others_currency'=>'[]'],$closed);
        $this->manifest['days'][$day->toDateString()]=$this->dayTotals+['opening'=>$opening,'closing'=>$this->funds];
    }

    private function sale(array $lines, CarbonImmutable $at, CarbonImmutable $day): void
    {
        $customer=mt_rand(1,100)<=40?$this->walkInId:$this->customers[array_rand($this->customers)];
        $subtotal=0;foreach($lines as $id=>$qty)$subtotal+=$this->catalog[$id]['price']*$qty;
        $discount=mt_rand(1,100)<=18?round($subtotal*.03/100)*100:0;
        $total=$subtotal-$discount;$credit=$customer!==$this->walkInId && mt_rand(1,100)<=16;
        $paid=$credit?round($total*.65/100)*100:$total;
        $method=mt_rand(1,100)<=67?$this->cashId:$this->bankId;
        $stamp=$at->toDateTimeString();$number='DEMO-VTE-'.$day->format('ymd').'-'.sprintf('%04d',count($this->manifest['ids']['sales']??[])+1);
        $employee=$this->employees[array_rand($this->employees)];
        $sale=$this->row('sales',['sale_no'=>$number,'sale_date'=>$day->toDateString(),'date_time'=>$stamp,'order_time'=>$stamp,'order_date_time'=>$stamp,'close_time'=>$stamp,'customer_id'=>$customer,'employee_id'=>$employee,'total_items'=>array_sum($lines),'sub_total'=>$subtotal,'sub_total_with_discount'=>$total,'total_payable'=>$total,'grand_total'=>$total,'paid_amount'=>$paid,'given_amount'=>$paid,'change_amount'=>0,'due_amount'=>$total-$paid,'previous_due'=>0,'disc'=>$discount,'disc_actual'=>$discount,'sub_total_discount_amount'=>$discount,'sub_total_discount_value'=>$discount,'total_discount_amount'=>$discount,'total_item_discount_amount'=>0,'vat'=>0,'delivery_charge'=>0,'rounding'=>0,'sale_vat_objects'=>'[]'],$stamp);
        foreach($lines as $id=>$qty){
            $item=$this->catalog[$id];
            $this->row('sale_details',['sales_id'=>$sale,'sale_id'=>$sale,'item_id'=>$id,'item_type'=>'General_Product','qty'=>$qty,'menu_unit_price'=>$item['price'],'menu_price_without_discount'=>$item['price'],'menu_price_with_discount'=>$item['price'],'purchase_price'=>$item['cost'],'menu_vat_percentage'=>0,'item_tax_amount'=>0,'menu_discount_value'=>0,'discount_amount'=>0,'menu_taxes'=>'[]','loyalty_point_earn'=>0,'item_seller_id'=>$employee,'is_promo_item'=>0],$stamp);
            $this->stock[$id]-=$qty;
        }
        $this->row('sale_payments',['sale_id'=>$sale,'payment_id'=>$method,'amount'=>$paid,'date'=>$day->toDateString(),'usage_point'=>0,'multi_currency_rate'=>1],$stamp);
        $this->dayTotals['sales']+=$paid;
        if($credit)$this->schedule($day->addDays(mt_rand(7,23)),['kind'=>'customer','customer_id'=>$customer,'amount'=>$total-$paid,'reference'=>$number]);
        if($this->enabled('menu_sale_returns') && !$credit && mt_rand(1,100)<=4){
            $id=array_key_first($lines);
            // No invoice discount on returned sales, so refund and line amounts agree exactly.
            if($discount===0)$this->schedule($day->addDays(mt_rand(1,7)),['kind'=>'return','sale_id'=>$sale,'customer_id'=>$customer,'item_id'=>$id,'sale_qty'=>$lines[$id],'amount'=>$this->catalog[$id]['price'],'reference'=>$number]);
        }
    }

    private function settle(array $event, CarbonImmutable $day): void
    {
        $stamp=$day->setTime(14,30)->toDateTimeString();$amount=$event['amount'];
        if($event['kind']==='supplier'){
            $this->row('supplier_payments',['supplier_id'=>$event['supplier_id'],'reference_no'=>'DEMO-FOUR-'.$event['reference'],'date'=>$day->toDateString(),'amount'=>$amount,'payment_method_id'=>$this->bankId,'note'=>'Règlement différé fictif fournisseur'],$stamp);$this->dayTotals['supplier']+=$amount;
        }elseif($event['kind']==='customer'){
            $this->row('customer_receives',['customer_id'=>$event['customer_id'],'reference_no'=>'DEMO-CLI-'.$event['reference'],'date'=>$day->toDateString(),'amount'=>$amount,'current_amount'=>$amount,'payment_method_id'=>$this->cashId,'note'=>'Règlement différé fictif de '.$event['reference']],$stamp);$this->dayTotals['customer']+=$amount;
        }else{
            $return=$this->row('sale_returns',['reference_no'=>'DEMO-RET-'.$event['reference'],'date'=>$day->toDateString(),'customer_id'=>$event['customer_id'],'sale_id'=>$event['sale_id'],'total_return_amount'=>$amount,'paid'=>$amount,'due'=>0,'payment_method_id'=>$this->cashId,'note'=>'Retour fictif sous 7 jours — produit remis en stock'],$stamp);
            $this->row('sale_return_details',['sale_return_id'=>$return,'sale_id'=>$event['sale_id'],'item_id'=>$event['item_id'],'item_type'=>'General_Product','sale_quantity_amount'=>$event['sale_qty'],'return_quantity_amount'=>1,'unit_price_in_sale'=>$amount,'unit_price_in_return'=>$amount],$stamp);
            $this->stock[$event['item_id']]++;$this->dayTotals['returns']+=$amount;
        }
    }

    private function expense(int $category,int $amount,CarbonImmutable $day,string $note): void
    {
        $this->row('expenses',['category_id'=>$this->expenseCategories[$category],'employee_id'=>$this->adminId,'reference_no'=>'DEMO-DEP-'.$day->format('ymd').'-'.$category,'date'=>$day->toDateString(),'amount'=>$amount,'payment_method_id'=>$this->cashId,'note'=>$note.' — démonstration'],($day->isSameDay($this->end)?$this->end->min($day->setTime(17,0)):$day->setTime(17,0))->toDateTimeString());$this->dayTotals['expenses']+=$amount;
    }

    private function summary(): array
    {
        $gross=(float)DB::table('sales')->where('company_id',$this->companyId)->sum('total_payable');
        $returns=(float)DB::table('sale_returns')->where('company_id',$this->companyId)->sum('total_return_amount');
        return ['from'=>$this->manifest['from'],'to'=>$this->manifest['to'],'outlets'=>1,'products'=>count($this->catalog),'customers'=>count($this->customers),'sales'=>count($this->manifest['ids']['sales']??[]),'purchases'=>count($this->manifest['ids']['purchases']??[]),'returns'=>count($this->manifest['ids']['sale_returns']??[]),'open_days'=>count($this->manifest['days']),'gross_revenue_fcfa'=>$gross,'net_revenue_after_returns_fcfa'=>$gross-$returns,'expenses_fcfa'=>(float)DB::table('expenses')->where('company_id',$this->companyId)->sum('amount'),'remaining_units'=>array_sum($this->stock),'closing_working_funds_fcfa'=>$this->funds];
    }
}
