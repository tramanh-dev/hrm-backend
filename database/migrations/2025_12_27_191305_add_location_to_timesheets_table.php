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
    Schema::table('timesheets', function (Blueprint $table) {
        // Kiểu decimal(10, 8) và (11, 8) là chuẩn để lưu tọa độ GPS
        $table->decimal('lat', 10, 8)->nullable()->after('check_out');
        $table->decimal('lng', 11, 8)->nullable()->after('lat');
        $table->string('device_info')->nullable()->after('lng'); // Lưu thêm thông tin thiết bị (tùy chọn)
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            //
        });
    }
};
