// Types for the admin read-only Inertia surface (F6).

export type AdminWidget = Record<string, number>;

export interface AdminMoneySummary {
    pending: number;
    rejected: number;
    amount: string;
    charge: string;
}

export interface AdminLoginCharts {
    browser: Record<string, number>;
    os: Record<string, number>;
    country: Record<string, number>;
}

export interface AdminChartEndpoints {
    depositWithdraw: string;
    transaction: string;
    chargeCommission: string;
    registration: string;
}

export interface AdminChartSeries {
    name: string;
    data: number[];
}

export interface AdminChartResponse {
    created_on: string[];
    data: AdminChartSeries[];
}

// Generic admin list row (explicitly mapped, secret-free) — used by F6b list pages.
export interface AdminGuardRow {
    id: number;
    username: string;
    fullname: string;
    email: string;
    mobile: string | null;
    balance: string;
    statusBadge: string;
    joinedHuman: string;
    detailUrl: string | null;
}
