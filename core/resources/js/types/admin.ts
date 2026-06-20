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

// Explicitly-mapped, secret-free list rows used by the F6b admin list pages.
export interface StatusChip {
    label: string;
    tone: string;
}

export interface AdminGuardRow {
    id: number;
    fullname: string;
    username: string;
    email: string;
    mobile: string | null;
    countryCode: string | null;
    countryName: string | null;
    joined: string;
    joinedHuman: string;
    detailUrl: string;
    kycDataUrl: string | null;
}

export interface AdminDepositRow {
    id: number;
    trx: string;
    gateway: string;
    initiated: string;
    initiatedHuman: string;
    userType: string;
    fullname: string;
    username: string | null;
    amount: string;
    charge: string;
    total: string;
    currency: string;
    status: StatusChip;
    detailUrl: string;
}

export interface AdminWithdrawRow {
    id: number;
    trx: string;
    method: string;
    initiated: string;
    initiatedHuman: string;
    userType: string;
    fullname: string;
    username: string | null;
    amount: string;
    charge: string;
    afterCharge: string;
    currency: string;
    status: StatusChip;
    detailUrl: string;
}

export interface AdminTicketRow {
    id: number;
    ticket: string;
    subject: string;
    name: string;
    userType: string;
    status: StatusChip;
    priority: StatusChip;
    lastReplyHuman: string;
    viewUrl: string;
}
