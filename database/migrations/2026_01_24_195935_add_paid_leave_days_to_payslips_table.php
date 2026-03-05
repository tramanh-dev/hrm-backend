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
            $table->integer('paid_leave_days')->default(0)->after('total_work_days');
            $table->integer('total_payable_days')->default(0)->after('paid_leave_days');
        });
    }

    public function down()
    {
        Schema::table('payslips', function (Blueprint $table) {
            $table->dropColumn(['paid_leave_days', 'total_payable_days']);
        });
    }
};
