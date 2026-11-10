/**
 * Every piece of copy on the public landing page lives in this file.
 *
 * Keep every claim true to what the app does: no pass rates, question counts
 * or guideline titles that are not in the product.
 */
export const landingCopy = {
    hero: {
        badge: 'For MRCP Part 1, MRCP Part 2, IMM and FCPS Part 2',
        title: 'Revise smarter. Walk into the exam knowing your score.',
        body: 'Practise exam-style MCQs by subject or past paper, with detailed explanations that cite NICE and WHO guidance where it applies. Sit timed mock exams and see exactly which subjects still need work.',
        primary: 'Find your exam',
        secondary: 'Try a free demo',
    },
    exams: {
        heading: 'Choose your exam',
        intro: 'Pick the exam you are sitting. MRCP 1 and IMM share the MRCPWorld 1 Qbank; MRCP 2 and FCPS Part 2 share the MRCPWorld 2 Qbank.',
        demo: 'Take a demo',
        signUp: 'Sign up',
        logIn: 'Log in',
        questions: (count: number): string => `${count}+ questions`,
        empty: 'Exams are being prepared. Check back shortly.',
    },
    features: {
        textbook: {
            title: 'A textbook that follows the syllabus',
            body: 'The extended textbook is arranged by subject, in short chapters. Read up on a weak area, then go straight to the questions on the same subject.',
        },
        build: {
            title: 'Build a test from exactly what you need',
            body: 'Choose a subject or a past paper and how many questions you want, then start. For exam practice, sit a timed mock: the clock runs on the server and the answers are revealed only when the mock ends.',
        },
        compare: {
            title: 'Am I on track? See how you compare',
            body: 'After each answer, see how other candidates answered the same question. Your performance page tracks accuracy by subject and by day, and the weekly leaderboard shows where you stand.',
        },
    },
    progress: {
        heading: 'Topic',
        attempted: 'Attempted',
        sampleLabel: 'Sample progress',
    },
    histogram: {
        title: 'Score distribution',
        low: '20%',
        high: '100%',
        you: 'Your score (outlined)',
        sample: 'Sample distribution',
        sampleNote: 'Shown with sample numbers until enough mock exams have been completed.',
        real: 'Real candidate results',
    },
    pricing: {
        heading: 'Simple plans',
        intro: 'Each Qbank is sold separately, for 3 or 5 months. It includes its main MCQ bank and every past paper MCQ.',
        choose: 'Subscribe',
        member: 'Already a member? Log in to renew',
        placeholderPrice: '[PRICE]',
        empty: 'Plans are being set up. Check back shortly.',
    },
    footer: {
        rights: 'All rights reserved.',
    },
    demo: {
        title: 'Free demo questions',
        intro: 'These sample questions show how the question bank and explanations look.',
        empty: 'No demo questions are available for this exam yet.',
        signUp: 'Create a free account',
        back: 'Back to the landing page',
    },
} as const;

export const landingSeo = {
    title: 'Revise smarter. Walk into the exam knowing your score.',
    description:
        'MCQ question bank, past paper MCQs, timed mock exams and an extended textbook for MRCP Part 1, MRCP Part 2, IMM and FCPS Part 2. Detailed explanations with NICE and WHO references where relevant.',
    ogTitle: 'MRCP World — MRCP question bank and mock exams',
    ogDescription: 'MCQ banks, past paper MCQs and timed mock exams for MRCP Part 1, MRCP Part 2, IMM and FCPS Part 2.',
    ogType: 'website',
};
