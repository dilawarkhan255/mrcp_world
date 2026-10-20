export interface PageSlice<T> {
    data: T[];
    current_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
export interface SessionSummary {
    id: number;
    total_questions: number;
    answered_count: number;
    correct_count: number;
    completed_at: string | null;
    created_at?: string;
}
export interface PracticeQuestion {
    position: number;
    body: string;
    options: Record<string, string>;
    selected_option: string | null;
    result?: { is_correct: boolean; correct_option: string; explanation: string | null };
}
