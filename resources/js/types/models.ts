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
    claimed_dates: string[] | null;
    schedule_id: number | null;
    notes: string | null;
}

export interface ShiftTakeoverOffer {
    id: number;
    schedule_id: number | null;
    schedule_exception_id: number | null;
    date: string;
    offered_by_caregiver_id: number;
    status: 'open' | 'claimed' | 'cancelled' | 'expired';
    claimed_by_caregiver_id: number | null;
    claimed_at: string | null;
    notes: string | null;
    offered_by?: Caregiver;
    claimed_by?: Caregiver;
}

export interface ShiftSwapRequest {
    id: number;
    requester_caregiver_id: number;
    requester_schedule_id: number | null;
    requester_schedule_exception_id: number | null;
    requester_date: string;
    target_caregiver_id: number;
    target_schedule_id: number | null;
    target_schedule_exception_id: number | null;
    target_date: string;
    status: 'pending' | 'accepted' | 'declined' | 'cancelled' | 'expired';
    responded_at: string | null;
    decline_reason: string | null;
    requester?: Caregiver;
    target?: Caregiver;
}

export interface ShiftTakeoverRequest {
    id: number;
    requester_caregiver_id: number;
    target_caregiver_id: number;
    target_schedule_id: number | null;
    target_schedule_exception_id: number | null;
    target_date: string;
    status: 'pending' | 'accepted' | 'declined' | 'cancelled' | 'expired';
    responded_at: string | null;
    decline_reason: string | null;
    message: string | null;
    requester?: Caregiver;
    target?: Caregiver;
}

export interface OpenSwapRequest {
    id: number;
    schedule_id: number | null;
    schedule_exception_id: number | null;
    date: string;
    requester_caregiver_id: number;
    status: 'open' | 'fulfilled' | 'cancelled' | 'expired';
    selected_offer_id: number | null;
    notes: string | null;
    requester?: Caregiver;
    offers?: OpenSwapOffer[];
}

export interface OpenSwapOffer {
    id: number;
    open_swap_request_id: number;
    offered_by_caregiver_id: number;
    offered_schedule_id: number | null;
    offered_schedule_exception_id: number | null;
    offered_date: string;
    status: 'pending' | 'accepted' | 'declined' | 'withdrawn';
    offered_by?: Caregiver;
}
