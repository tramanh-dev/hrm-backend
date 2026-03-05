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
    Schema::table('leaves', function (Blueprint $table) {
        $table->string('leave_type')->nullable()->after('reason'); // Nghỉ phép năm, Nghỉ lễ...
        $table->float('duration_days')->default(0)->after('end_date'); // Số ngày nghỉ thực tế
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leaves', function (Blueprint $table) {
            //
        });
    }
};
