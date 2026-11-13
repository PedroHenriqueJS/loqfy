<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('checkout_photo_hash', 16)->nullable();
            $table->string('return_photo_hash', 16)->nullable();
            $table->json('checks')->nullable();
        });

        Schema::table('financial_entries', function (Blueprint $table) {
            $table->string('receipt_photo_hash', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inspections', fn (Blueprint $table) => $table->dropColumn(['checkout_photo_hash', 'return_photo_hash', 'checks']));
        Schema::table('financial_entries', fn (Blueprint $table) => $table->dropColumn('receipt_photo_hash'));
    }
};
