export type Tone = 'emerald' | 'amber' | 'rose' | 'sky' | 'stone' | 'neutral';

export interface StatusValue {
    value: string;
    label: string;
    tone: Tone;
}

export type PunchTypeName = 'arrival' | 'break_start' | 'break_end' | 'departure';

export interface PunchTypeValue {
    value: PunchTypeName;
    label: string;
    buttonLabel: string;
}

export interface AuthUser {
    id: number;
    name: string;
    initials: string;
    role: 'admin' | 'staff';
    department: string | null;
}

export interface WorkScheduleInput {
    starts_at: string;
    ends_at: string;
}

export interface Employee {
    id: number;
    name: string;
    initials: string;
    phone: string | null;
    email: string | null;
    departmentId: number | null;
    department?: string | null;
    isActive: boolean;
    mustChangePassword?: boolean;
    workScheduleCount?: number;
    schedules?: (WorkScheduleInput & { weekday: number })[];
}

export interface Department {
    id: number;
    name: string;
    employeeCount?: number;
}

export interface DayPunch {
    id: number;
    type: PunchTypeValue;
    time: string;
    isManual: boolean;
    isOffline: boolean;
}

export interface PunchCorrectionEntry {
    id: number;
    date: string;
    dateLabel: string;
    action: 'added' | 'updated' | 'deleted';
    actionLabel: string;
    summary: string;
    reason: string;
    author: string;
    at: string;
}

export interface DailyAttendance {
    date: string;
    dateLabel: string;
    weekdayShort: string;
    weekdayLong: string;
    dayNumber: string;
    monthDay: string;
    isToday: boolean;
    isScheduled: boolean;
    scheduleLabel: string;
    firstArrival: string | null;
    lastDeparture: string | null;
    scheduledMinutes: number;
    workedMinutes: number;
    breakMinutes: number;
    lateMinutes: number;
    overtimeMinutes: number;
    status: StatusValue;
    leave: { type: string } | null;
    punches: DayPunch[];
}

export interface AttendanceSummary {
    workedDays: number;
    absentDays: number;
    leaveDays: number;
    lateCount: number;
    lateMinutes: number;
    workedMinutes: number;
    breakMinutes: number;
    overtimeMinutes: number;
    scheduledMinutes: number;
    incompleteDays: number;
    punctualityRate: number | null;
    attendanceRate: number | null;
}

export interface ReportMonth {
    value: string;
    label: string;
    previous: string;
    next: string | null;
}

export interface Punch {
    id: number;
    type: PunchTypeValue;
    date: string;
    dateLabel: string;
    dayLabel: string;
    time: string;
    timeWithSeconds: string;
    month: string;
    employee?: { id: number; name: string; initials: string; department: string | null };
    kiosk?: string;
    lateMinutes?: number;
    isOffline: boolean;
    syncedAt: string | null;
    isManual: boolean;
    isCorrected?: boolean;
}

export interface LeaveRequest {
    id: number;
    type: { value: string; label: string };
    status: StatusValue;
    startsOn: string;
    endsOn: string;
    startsLabel: string;
    endsLabel: string;
    shortPeriod: string;
    dayCount: number;
    startDay: string;
    startMonth: string;
    timing: string;
    submittedAt: string | null;
    reason: string | null;
    reviewNote: string | null;
    reviewedAt: string | null;
    reviewer?: string | null;
    employee?: { id: number; name: string; initials: string };
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    total: number;
    from: number | null;
    to: number | null;
}

export interface SharedProps {
    [key: string]: unknown;
    app: {
        instituteName: string;
        lateToleranceMinutes: number;
    };
    auth: {
        user: AuthUser | null;
    };
    flash: {
        status: string | null;
    };
    pendingLeaveCount: number;
    errors: Record<string, string>;
}
