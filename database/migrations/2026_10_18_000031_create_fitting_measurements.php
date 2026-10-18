<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5.2 Fitting AI — catatan pengukuran fitting per pelanggan.
 * Yang disimpan HANYA angka hasil ukur (PD binokular/monokular,
 * tinggi pupil, lebar wajah) + nilai rujukan manual bila diambil.
 * Foto/landmark TIDAK pernah disimpan (pemrosesan di perangkat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fitting_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers');
            $table->foreignId('prescription_id')->nullable()->constrained('prescriptions')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('measured_by')->constrained('users');
            $table->string('method', 20)->default('camera');
            $table->decimal('pd_binocular', 4, 1)->nullable();
            $table->decimal('pd_mono_od', 4, 1)->nullable();
            $table->decimal('pd_mono_os', 4, 1)->nullable();
            $table->decimal('pupil_height_od', 4, 1)->nullable();
            $table->decimal('pupil_height_os', 4, 1)->nullable();
            $table->decimal('face_width', 5, 1)->nullable();
            $table->decimal('pd_reference', 4, 1)->nullable();
            $table->string('frame_size_recommendation', 10)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fitting_measurements');
    }
};
