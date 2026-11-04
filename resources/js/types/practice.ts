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
export interface QuestionLabels {
    subject: string | null;
    system: string | null;
    repeats: number;
    reference: string | null;
}
export interface QuestionReference {
    source: string;
    title: string;
    url: string | null;
    year: string | null;
}
export interface ImageLink {
    label: string;
    url: string;
}
/** Aggregated answers of every student; null until enough students answered. */
export interface CandidateStats {
    attempts: number;
    correct_percent: number;
    average_seconds: number | null;
    options: Record<string, number>;
    topic_percent: number | null;
}
export interface QuestionResult {
    is_correct: boolean;
    correct_option: string;
    explanation: string | null;
    /** Server-rendered Markdown with raw HTML stripped. */
    explanation_html?: string | null;
    key_points_html?: string | null;
    mnemonics_html?: string | null;
    references?: QuestionReference[];
    youtube_id?: string | null;
    image_links?: ImageLink[];
    image_search?: string | null;
    /** Live search links on Radiopaedia, DermNet, LITFL and EyeWiki for the search term. */
    image_searches?: { site: string; label: string; url: string }[];
    time_seconds?: number | null;
    candidates?: CandidateStats | null;
}
export interface PracticeQuestion {
    position: number;
    code?: string | null;
    body: string;
    /** Server-rendered Markdown with raw HTML stripped. */
    body_html?: string | null;
    options: Record<string, string>;
    selected_option: string | null;
    is_read?: boolean;
    labels?: QuestionLabels;
    question_id?: number;
    is_bookmarked?: boolean;
    bookmark_id?: number | null;
    note?: string | null;
    result?: QuestionResult;
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
    read?: boolean;
    /** Null while the answer is hidden (an open mock) or unanswered. */
    correct?: boolean | null;
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
export interface CommentThread {
    id: number;
    body: string;
    created_at: string | null;
    is_hidden: boolean;
    is_pinned: boolean;
    author: string;
    author_role: string;
    is_mine: boolean;
    question: { id: number; code: string; body: string } | null;
    replies: CommentThread[];
}
export interface CommentQuestion {
    id: number;
    code: string;
    body: string;
}
export interface BookmarkItem {
    id: number;
    question_id: number;
    code: string | null;
    body: string | null;
    category: string | null;
    note: string | null;
    created_at: string | null;
}
