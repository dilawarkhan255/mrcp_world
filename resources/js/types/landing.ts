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
    duration_days: number;
    price: string;
    amount: string;
    currency: string;
}

export interface LandingPlanGroup {
    duration_days: number;
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
