<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Add order_session_id
            $table->foreignId('order_session_id')->after('order_number')->constrained('order_sessions')->cascadeOnDelete();
            
            // Drop old columns since now handled by session
            $table->dropForeign(['table_id']);
            $table->dropColumn(['table_id', 'customer_name']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Restore old columns
            $table->foreignId('table_id')->after('order_number')->constrained('tables')->cascadeOnDelete();
            $table->string('customer_name')->nullable()->after('table_id');
            
            // Drop session column
            $table->dropForeign(['order_session_id']);
            $table->dropColumn('order_session_id');
        });
    }
};
