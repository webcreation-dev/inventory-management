<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('sales','online_yes_no')) Schema::table('sales',fn(Blueprint $t)=>$t->string('online_yes_no',3)->default('No')->index());
    }
    public function down(): void { /* Preserve the channel indicator. */ }
};
