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
    Schema::create('timesheets', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->constrained()->onDelete('cascade');
        $table->date('work_date'); // Ngày làm việc (VD: 2023-12-04)
        $table->time('check_in')->nullable();  // Giờ vào
        $table->time('check_out')->nullable(); // Giờ ra
        $table->float('day_count')->default(0); // Số công (0.5 hoặc 1)
        $table->timestamps();
        
        // Mỗi nhân viên chỉ có 1 dòng chấm công cho 1 ngày
        $table->unique(['user_id', 'work_date']); 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};
