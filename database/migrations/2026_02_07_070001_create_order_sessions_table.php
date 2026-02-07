<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('table_id')->constrained('tables')->cascadeOnDelete();
            $table->string('customer_name');
            $table->string('status', 20)->default('active'); // active, completed, cancelled
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['table_id', 'customer_name']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sessions');
    }
};
