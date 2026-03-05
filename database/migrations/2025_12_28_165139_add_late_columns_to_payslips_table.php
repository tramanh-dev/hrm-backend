<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up()
{
    Schema::table('payslips', function (Blueprint $table) {
        $table->integer('total_late_minutes')->default(0); // Xóa after()
        $table->decimal('late_deduction', 15, 2)->default(0); // Xóa after()
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            //
        });
    }
};
