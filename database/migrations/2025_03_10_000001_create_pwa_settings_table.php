<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pwa_settings', function (Blueprint $table) {
            $table->id();
            $table->string('app_name')->default('Retail POS');
            $table->string('short_name')->default('RPOS');
            $table->string('theme_color', 20)->default('#7367f0');
            $table->string('background_color', 20)->default('#ffffff');
            $table->string('logo')->nullable();
            $table->string('start_url')->default('/');
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pwa_settings');
    }
};
