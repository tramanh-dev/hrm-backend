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
    Schema::create('salary_levels', function (Blueprint $table) {
        $table->id(); // BẮT BUỘC phải có cái này
        $table->string('level_name');
        $table->decimal('base_salary', 15, 2);
        $table->decimal('allowance', 15, 2)->default(0);
        $table->timestamps(); // Nên có để quản lý thời gian tạo/sửa
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_levels');
    }
};
