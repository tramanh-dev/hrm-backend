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
    Schema::create('company_settings', function (Blueprint $table) {
        $table->id();
        $table->string('company_name')->default('Văn phòng chính');
        
        // Tọa độ GPS của công ty
        $table->decimal('latitude', 10, 8); 
        $table->decimal('longitude', 11, 8);
        
        // Bán kính cho phép chấm công (ví dụ 100 mét)
        $table->integer('radius')->default(100); 
        
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
