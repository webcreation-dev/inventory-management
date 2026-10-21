<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('customers', 'is_installment_customer')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('is_installment_customer', 3)->default('No')->index();
            });
        }
    }

    public function down(): void
    {
        // Preserve customer classification if this recovery migration is rolled back.
    }
};
