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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('salary_level_id')->nullable()->constrained('salary_levels');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        // Phải xóa khóa ngoại và cột khi rollback
        $table->dropForeign(['salary_level_id']);
        $table->dropColumn('salary_level_id');
    });
}
};
