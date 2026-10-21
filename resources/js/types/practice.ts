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
export interface MockPreset {
    title: string;
    questions: number;
    minutes: number;
}
export interface MockLimits {
    maxQuestions: number;
    maxMinutes: number;
}
export interface MockAttempt {
    id: number;
    total_questions: number;
    answered_count: number;
    correct_count: number;
    completed_at: string | null;
    started_at: string | null;
    duration_seconds: number | null;
}
export interface MockSession {
    id: number;
    total_questions: number;
    answered_count: number;
    correct_count: number;
    completed_at: string | null;
    started_at: string | null;
    expires_at: string | null;
    duration_seconds: number | null;
}
export interface NavigatorItem {
    position: number;
    answered: boolean;
}
export interface ReviewItem {
    id: number;
    body: string;
    options: Record<string, string>;
    student_option: string | null;
    correct_option: string;
    explanation: string | null;
    wrong_count: number;
    last_answered_at: string | null;
}
export interface PerformanceSummary {
    answered: number;
    correct: number;
    accuracy: number;
    sessions: number;
    completed: number;
    mocks: number;
}
export interface TrendPoint {
    day: string;
    answered: number;
    correct: number;
}
export interface CategoryPerformance {
    id: number;
    name: string;
    answered: number;
    correct: number;
    accuracy: number;
}
export interface WeakQuestion {
    id: number;
    body: string;
    wrong_count: number;
}
