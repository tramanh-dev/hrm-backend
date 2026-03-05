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
        Schema::create('salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('salary_level')->nullable(); // Bậc lương (ví dụ: L_THANG)
            $table->decimal('base_salary', 15, 2); // Mức lương chính
            $table->decimal('allowance_position', 15, 2)->default(0); // Phụ cấp chức vụ
            $table->decimal('allowance_phone', 15, 2)->default(0); // Phụ cấp điện thoại
            $table->decimal('allowance_meal', 15, 2)->default(0); // Phụ cấp cơm
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salaries');
    }
};
