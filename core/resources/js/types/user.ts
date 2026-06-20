// Types for the authenticated user (web guard) Inertia surface.

export interface AuthUser {
    id: number;
    username: string;
    fullname: string;
    email: string;
    image: string | null;
    kv: number; // KYC status: 0 unverified, 1 verified, 2 pending
    ts: number; // 2FA status
}

export interface NavItem {
    label: string;
    url: string;
    icon: string;
}

export interface UserNav {
    primary: NavItem[];
    actions: NavItem[];
    account: NavItem[];
}

export type Modules = Record<string, boolean>;

export interface FlashNotify {
    // session('notify') tuples: [status, message]
    notify: Array<[string, string]>;
}

export interface DashboardWallet {
    balance: string;
    symbol: string;
    code: string;
    transferUrl: string;
}

export interface TotalBalance {
    amount: string;
    symbol: string;
    code: string;
}

export interface DashboardTransaction {
    trxType: string;
    remark: string;
    date: string;
    details: string;
    receiver: string | null;
    amount: string;
    code: string;
    trx: string;
    charge: number;
    chargeType: string;
    chargeFmt: string;
    beforeCharge: string;
    postBalance: string;
}

export interface DashboardKyc {
    status: number;
    rejectionReason: string | null;
    messages: { reject: string | null; required: string | null; pending: string | null };
    formUrl: string;
    dataUrl: string;
}
