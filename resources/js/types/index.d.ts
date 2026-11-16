export type Tone = 'emerald' | 'amber' | 'rose' | 'sky' | 'stone' | 'neutral';

export interface StatusValue {
    value: string;
    label: string;
    tone: Tone;
}

export type PunchTypeName = 'arrival' | 'break_start' | 'break_end' | 'departure';

export interface KioskPunch {
    id: number;
    type: PunchTypeName;
    label: string;
    message: string;
    time: string;
}

export interface PunchTypeValue {
    value: PunchTypeName;
    label: string;
    buttonLabel: string;
}

export interface AuthUser {
    id: number;
    name: string;
    initials: string;
    role: UserRoleName;
    roleLabel: string;
    department: string | null;
    shop: string | null;
}

export type UserRoleName = 'super_admin' | 'admin' | 'staff';

export interface ShopOption {
    id: number;
    name: string;
    isActive?: boolean;
}

export interface Shop {
    id: number;
    name: string;
    address: string | null;
    phone: string | null;
    isActive: boolean;
    staffCount?: number;
    kioskCount?: number;
    managers?: { id: number; name: string; initials: string; isActive: boolean }[];
    kiosks?: { id: string; name: string; url: string; isActive: boolean }[];
    departments?: { id: number; name: string; staffCount: number }[];
}

export interface Manager {
    id: number;
    name: string;
    initials: string;
    email: string | null;
    phone: string | null;
    role: Exclude<UserRoleName, 'staff'>;
    roleLabel: string;
    isActive: boolean;
    mustChangePassword: boolean;
    shopIds?: number[];
    shops?: string[];
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
    shopId: number | null;
    shop?: string | null;
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
    shopId: number;
    shop?: string | null;
    employeeCount?: number;
}

export interface DayPunch {
    id: number;
    type: PunchTypeValue;
    time: string;
    isManual: boolean;
    isOffline: boolean;
    viaKioskLogin: boolean;
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
    holiday: string | null;
    punches: DayPunch[];
}

export interface AttendanceSummary {
    workedDays: number;
    absentDays: number;
    leaveDays: number;
    holidayDays: number;
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
    viaKioskLogin: boolean;
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
    shopContext: { current: ShopOption | null; shops: ShopOption[] } | null;
    pendingLeaveCount: number;
    unsatisfiedReviewCount: number;
    errors: Record<string, string>;
}
