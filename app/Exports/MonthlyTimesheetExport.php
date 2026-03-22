<?php

namespace App\Exports;

use App\Models\Timesheet;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Carbon\Carbon;

class MonthlyTimesheetExport implements FromCollection, WithHeadings, WithMapping
{
    protected $fromDate;
    protected $toDate;

    // Nhận khoảng ngày từ Controller
    public function __construct($fromDate, $toDate)
    {
        $this->fromDate = $fromDate;
        $this->toDate   = $toDate;
    }

    public function collection()
    {
        return Timesheet::with('user')
            ->whereBetween('work_date', [
                $this->fromDate->toDateString(),
                $this->toDate->toDateString()
            ])
            ->orderBy('work_date', 'asc')
            ->get();
    }

    public function headings(): array
    {
        return ["Mã NV", "Tên Nhân Viên", "Ngày làm", "Giờ Vào", "Giờ Ra", "Công"];
    }


    public function map($row): array
    {
        $dayCount = $row->day_count;

        if (($dayCount === 0 || $dayCount === null) && $row->check_in && $row->check_out) {
            $checkIn  = Carbon::parse($row->check_in);
            $checkOut = Carbon::parse($row->check_out);

            $hoursWorked = $checkOut->diffInMinutes($checkIn) / 60;

            if ($hoursWorked >= 7) {
                $dayCount = 1;
            } elseif ($hoursWorked >= 3.5) {
                $dayCount = 0.5;
            } else {
                $dayCount = 0;
            }
        }

        return [
            $row->user_id,
            $row->user->name ?? 'N/A',
            $row->work_date,
            $row->check_in,
            $row->check_out ?? '--:--',
            $dayCount,
        ];
    }
}
