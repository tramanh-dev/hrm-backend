<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Thêm dòng này:
            $table->string('phone_number')->nullable()->after('email');
            // nullable() nghĩa là cho phép để trống, lỡ nhập không có sđt cũng không lỗi
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // Thêm dòng này để lỡ muốn xóa thì xóa sạch
            $table->dropColumn('phone_number');
        });
    }
};
