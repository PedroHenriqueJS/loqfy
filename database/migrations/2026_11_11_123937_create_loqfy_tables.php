<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->string('description')->nullable();
            $table->json('aliases');
            $table->decimal('daily_rate', 8, 2);
            $table->unsignedInteger('quantity_total');
            $table->timestamps();
        });

        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('neighborhoods');
            $table->decimal('fee', 8, 2);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kind')->default('person');
            $table->string('cnpj', 14)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('notes')->nullable();
            $table->timestamp('document_verified_at')->nullable();
            $table->timestamps();
        });

        Schema::create('rental_requests', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name')->nullable();
            $table->string('channel')->default('whatsapp');
            $table->text('raw_text');
            $table->string('status')->default('pending');
            $table->json('extraction')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_request_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('origin')->default('manual');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->boolean('needs_review')->default(false);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedInteger('days')->default(0);
            $table->foreignId('delivery_zone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('neighborhood')->nullable();
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->json('issues');
            $table->string('confidence')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment');
            $table->foreignId('substitutes_equipment_id')->nullable()->constrained('equipment');
            $table->string('requested_term')->nullable();
            $table->unsignedInteger('quantity');
            $table->decimal('daily_rate', 8, 2);
            $table->decimal('line_total', 10, 2);
            $table->boolean('available')->default(true);
            $table->timestamps();
        });

        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_name')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('reserved');
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('rental_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipment');
            $table->unsignedInteger('quantity');
            $table->decimal('daily_rate', 8, 2);
            $table->timestamps();
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_item_id')->constrained()->cascadeOnDelete();
            $table->string('checkout_photo')->nullable();
            $table->string('return_photo')->nullable();
            $table->string('status')->default('awaiting_return');
            $table->json('ai_result')->nullable();
            $table->text('error')->nullable();
            $table->string('decision')->nullable();
            $table->string('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_entries', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 10, 2);
            $table->date('due_date');
            $table->date('paid_at')->nullable();
            $table->string('origin')->default('manual');
            $table->string('receipt_photo')->nullable();
            $table->foreignId('rental_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->string('feature');
            $table->string('model');
            $table->nullableMorphs('subject');
            $table->json('output')->nullable();
            $table->json('tool_calls')->nullable();
            $table->unsignedInteger('steps')->default(0);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('success')->default(true);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['ai_runs', 'financial_entries', 'inspections', 'rental_items', 'rentals', 'quote_items', 'quotes', 'rental_requests', 'customers', 'delivery_zones', 'equipment'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
