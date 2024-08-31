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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->integer('debit')->default(0);
            $table->integer('credit')->default(0);
            $table->integer('solde')->default(0);
            $table->foreign('client_id')->references('id')->on('clients'); 
            $table->unsignedBigInteger('client_id');
            // $table->foreign('user_id')->references('id')->on('users'); 
            // $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
