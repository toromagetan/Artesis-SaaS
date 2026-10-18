<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2.3 — Order lab (Blueprint B.6): pekerjaan yang dipesan ke lab
 * internal/eksternal per penjualan. Status berurutan waiting →
 * ordered → received; lab_cost mengalir ke HPP saat received
 * (jurnal lewat peristiwa LabOrderReceived).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('sale_id')->constrained('sales');
            $table->string('lab_name', 100);
            $table->enum('status', ['waiting', 'ordered', 'received'])->default('waiting');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->decimal('lab_cost', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sale_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_orders');
    }
};
