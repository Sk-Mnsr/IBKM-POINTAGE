<?php

namespace App\Support;

use App\Enums\AttendanceStatus;
use Illuminate\Support\Collection;

class AttendanceSummary
{
    public int $workedDays = 0;

    public int $absentDays = 0;

    public int $leaveDays = 0;

    public int $holidayDays = 0;

    public int $lateCount = 0;

    public int $lateMinutes = 0;

    public int $workedMinutes = 0;

    public int $breakMinutes = 0;

    public int $overtimeMinutes = 0;

    public int $scheduledMinutes = 0;

    public int $incompleteDays = 0;

    /**
     * @param  Collection<int|string, DailyAttendance>  $days
     */
    public static function fromDays(Collection $days): self
    {
        $summary = new self;

        foreach ($days as $day) {
            $summary->add($day);
        }

        return $summary;
    }

    /**
     * @param  iterable<AttendanceSummary>  $summaries
     */
    public static function combine(iterable $summaries): self
    {
        $total = new self;

        foreach ($summaries as $summary) {
            foreach (get_object_vars($summary) as $property => $value) {
                $total->{$property} += $value;
            }
        }

        return $total;
    }

    public function add(DailyAttendance $day): void
    {
        if ($day->status === AttendanceStatus::Upcoming || $day->date->isAfter(today())) {
            return;
        }

        if ($day->countsAsWorked()) {
            $this->workedDays++;
        }

        if ($day->status === AttendanceStatus::Absent) {
            $this->absentDays++;
        }

        if ($day->status === AttendanceStatus::OnLeave) {
            $this->leaveDays++;
        }

        if ($day->holiday !== null && $day->schedule !== null) {
            $this->holidayDays++;
        }

        if ($day->status === AttendanceStatus::Incomplete) {
            $this->incompleteDays++;
        }

        if ($day->isLate()) {
            $this->lateCount++;
            $this->lateMinutes += $day->lateMinutes;
        }

        $this->workedMinutes += $day->workedMinutes;
        $this->breakMinutes += $day->breakMinutes;
        $this->overtimeMinutes += $day->overtimeMinutes;

        if ($day->status !== AttendanceStatus::OnLeave) {
            $this->scheduledMinutes += $day->scheduledMinutes;
        }
    }

    /**
     * @return array<string, int|null>
     */
    public function toArray(): array
    {
        return [
            ...get_object_vars($this),
            'punctualityRate' => $this->punctualityRate(),
            'attendanceRate' => $this->attendanceRate(),
        ];
    }

    public function punctualityRate(): ?int
    {
        if ($this->workedDays === 0) {
            return null;
        }

        return (int) round((($this->workedDays - $this->lateCount) / $this->workedDays) * 100);
    }

    public function attendanceRate(): ?int
    {
        $expectedDays = $this->workedDays + $this->absentDays;

        if ($expectedDays === 0) {
            return null;
        }

        return (int) round(($this->workedDays / $expectedDays) * 100);
    }
}
