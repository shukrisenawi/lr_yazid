export type User = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    profile_version?: number;
    role: 'admin' | 'customer';
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    isVip: boolean;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
