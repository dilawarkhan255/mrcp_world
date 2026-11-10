export interface LandingExam {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    code: string;
    question_count: number;
}

export interface LandingPlan {
    id: number;
    name: string;
    /** The Qbank the plan unlocks, and the exams it covers. */
    qbank?: string | null;
    exams?: string[];
    duration?: string;
    duration_days: number;
    price: string;
    amount: string;
    currency: string;
}

/** The plans of one Qbank (for example 3 months and 5 months). */
export interface LandingPlanGroup {
    qbank: string | null;
    exams: string[];
    plans: LandingPlan[];
}

export interface HistogramBucket {
    start: number;
    end: number;
    count: number;
}

export interface LandingHistogram {
    buckets: HistogramBucket[];
    is_sample: boolean;
    candidates: number;
}
