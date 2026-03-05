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
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('month');
            $table->integer('year');
            $table->decimal('total_work_days', 4, 1); // Tổng công từ bảng timesheets
            $table->decimal('bonus', 15, 2)->default(0); // Tiền thưởng thêm
            $table->decimal('deduction', 15, 2)->default(0); // Tiền phạt/trừ
            $table->decimal('final_salary', 15, 2); // Thực nhận cuối cùng
            $table->string('status')->default('pending'); // pending (chờ), paid (đã trả)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
