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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role')->nullable();
            $table->string('name', 55);
            $table->string('email', 55)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('zk_user_id', 20)->nullable();
            $table->decimal('salary', 10, 3)->nullable();
            $table->decimal('commission', 10, 2)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('open_cash_drawer', ['Yes', 'No'])->default('No');
            $table->string('photo', 255)->nullable();
            $table->string('language', 55)->default('english');
            $table->timestamp('last_login')->nullable();
            $table->string('question', 55)->nullable();
            $table->enum('will_login', ['Yes', 'No'])->default('Yes');
            $table->string('answer', 55)->nullable();
            $table->enum('active_status', ['Active', 'Inactive'])->default('Active');
            $table->string('discount_permission_code', 55)->nullable();
            $table->string('discount_amt', 11)->nullable();
            $table->string('start_date', 55)->nullable();
            $table->string('end_date', 55)->nullable();
            $table->string('is_saas', 55)->nullable();
            $table->boolean('is_delatable')->default(true);
            $table->string('outlet_id', 255)->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->enum('del_status', ['Live', 'Deleted'])->default('Live');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

