<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('reasons', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code', 100);
            $table->string('description');
            $table->enum('type', ['REJECT', 'REWORK']);
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('reasons')->insert([
            ['code' => 'STITCH_DEFECT', 'description' => 'Stitch defect', 'type' => 'REJECT', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'DIMENSION_MISMATCH', 'description' => 'Dimension mismatch', 'type' => 'REJECT', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'MATERIAL_DAMAGE', 'description' => 'Material damage', 'type' => 'REJECT', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'OTHER_REJECT', 'description' => 'Other', 'type' => 'REJECT', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'BENT_MISALIGNED', 'description' => 'Bent / misaligned', 'type' => 'REWORK', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'SURFACE_FINISH', 'description' => 'Surface finish', 'type' => 'REWORK', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'MISSING_HOLE', 'description' => 'Missing hole / feature', 'type' => 'REWORK', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'OTHER_REWORK', 'description' => 'Other', 'type' => 'REWORK', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reasons');
    }
};
