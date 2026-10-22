<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create supplier, purchase, product detail, and stock movement tables.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('product_retail_details', function (Blueprint $table) {
            $table->unsignedInteger('product_id')->primary();
            $table->string('barcode')->nullable()->unique();
            $table->string('unit', 24)->default('piece');
            $table->unsignedInteger('minimum_stock')->default(0);
            $table->decimal('purchase_price', 12, 4)->nullable();
            $table->unsignedInteger('supplier_id')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->nullOnDelete();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->string('number')->unique();
            $table->unsignedInteger('supplier_id');
            $table->unsignedInteger('inventory_source_id');
            $table->string('status', 32)->default('ordered');
            $table->timestamp('ordered_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('supplier_id')->references('id')->on('suppliers');
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources');
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('purchase_order_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('qty_ordered');
            $table->unsignedInteger('qty_received')->default(0);
            $table->decimal('unit_cost', 12, 4);
            $table->timestamps();

            $table->foreign('purchase_order_id')->references('id')->on('purchase_orders')->cascadeOnDelete();
            $table->foreign('product_id')->references('id')->on('products');
            $table->unique(['purchase_order_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('inventory_source_id');
            $table->string('type', 32);
            $table->integer('quantity_change');
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('reference_type', 32)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at');

            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('inventory_source_id')->references('id')->on('inventory_sources');
            $table->index(['product_id', 'inventory_source_id', 'created_at'], 'stock_movements_product_source_date_idx');
            $table->index(['batch_number', 'expires_at'], 'stock_movements_batch_expiry_idx');
        });
    }

    /**
     * Drop procurement tables in dependency order.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('product_retail_details');
        Schema::dropIfExists('suppliers');
    }
};
