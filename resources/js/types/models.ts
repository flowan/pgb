export interface Client {
    id: number;
    budget_holder_id: number;
    name: string;
    date_of_birth: string;
    notes: string | null;
    caregivers?: Caregiver[];
    budget_categories?: BudgetCategory[];
    caregivers_count?: number;
}

export interface Caregiver {
    id: number;
    client_id: number;
    user_id: number | null;
    name: string;
    type: 'parent' | 'care_worker' | 'day_care' | 'zzp' | 'other';
    hourly_rate: string | null;
}

export interface BudgetCategory {
    id: number;
    client_id: number;
    name: string;
    allocated_amount: string;
    spent_amount: string;
    expenses?: BudgetExpense[];
}

export interface BudgetExpense {
    id: number;
    budget_category_id: number;
    caregiver_id: number | null;
    description: string;
    amount: string;
    date: string;
    caregiver?: Caregiver;
}

export interface Schedule {
    id: number;
    client_id: number;
    caregiver_id: number;
    day_of_week: number;
    start_time: string;
    end_time: string;
    notes: string | null;
    caregiver?: Caregiver;
    exceptions?: ScheduleException[];
}

export interface ScheduleException {
    id: number;
    schedule_id: number | null;
    client_id: number;
    caregiver_id: number;
    date: string;
    start_time: string;
    end_time: string;
    type: 'cancelled' | 'modified' | 'added';
    notes: string | null;
    caregiver?: Caregiver;
}

export interface AvailabilitySlot {
    id: number;
    client_id: number;
    day_of_week: number | null;
    date: string | null;
    start_time: string;
    end_time: string;
    status: 'open' | 'claimed';
    claimed_by: number | null;
    claimed_at: string | null;
    notes: string | null;
}
