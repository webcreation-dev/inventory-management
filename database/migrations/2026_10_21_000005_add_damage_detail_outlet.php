<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('damage_details','outlet_id')) Schema::table('damage_details',fn(Blueprint $t)=>$t->unsignedBigInteger('outlet_id')->nullable()->index());
    }
    public function down(): void { /* Preserve the outlet indicator. */ }
};
