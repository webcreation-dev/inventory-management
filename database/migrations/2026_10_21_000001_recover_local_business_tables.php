<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!app()->environment('local') && !config('recovery.enabled')) {
            throw new RuntimeException('Set ALLOW_RECOVERED_SCHEMA=true to explicitly enable the inferred schema.');
        }
        $definitions = json_decode(file_get_contents(database_path('local-recovery-schema.json')), true, 512, JSON_THROW_ON_ERROR);
        foreach ($definitions as $name => $columns) {
            if (Schema::hasTable($name)) continue;
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach ($columns as $column) {
                    if ($column === 'del_status') {
                        $table->string($column, 20)->default('Live');
                    } elseif (str_ends_with($column, '_id') && !in_array($column, ['applicable_tax_id', 'outlet_id'])) {
                        $table->unsignedBigInteger($column)->nullable()->index();
                    } elseif (preg_match('/(?:price|amount|quantity|balance|total|discount|margin|rate|salary|stock|paid|due|cost|charge|conversion|point)/', $column)
                        && !preg_match('/(?:type|status|date|method|information|details|string|title|note|code|name|account|maintain|position|tax)/', $column)) {
                        $table->decimal($column, 20, 6)->nullable()->default(0);
                    } else {
                        $table->longText($column)->nullable();
                    }
                }
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Recovery tables may contain user data; do not delete them automatically.
    }
};
