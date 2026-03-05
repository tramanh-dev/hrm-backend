<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            
            // Tên công việc
            $table->string('title'); 
            
            // Mô tả chi tiết công việc
            $table->text('description')->nullable(); 

            // Trạng thái công việc: 0=Chưa giao, 1=Đã giao, 2=Đang làm, 3=Hoàn thành chờ duyệt, 4=Đã hoàn thành
            $table->unsignedSmallInteger('status')->default(1); 
            
            // Người được giao
            // Khóa ngoại liên kết tới cột 'id' trong bảng 'users'
            $table->foreignId('assigned_to_user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // Người tạo (HR/Admin)
            $table->foreignId('created_by_user_id')
                  ->constrained('users')
                  ->onDelete('cascade');

            // Thời hạn (Deadline)
            $table->date('due_date')->nullable();
            
            // Nội dung báo cáo của nhân viên (sẽ dùng sau)
            $table->text('report_content')->nullable(); 

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
