/**
 * Every piece of copy on the public landing page lives in this file.
 *
 * Paragraphs that still need real wording are marked with [YOUR COPY HERE] and
 * are shown on the page as that visible placeholder, so nothing is silently
 * invented.
 */
export const landingCopy = {
    topBar: {
        exams: 'Exams',
        features: 'Features',
        pricing: 'Pricing',
        logIn: 'Log in',
        email: 'Email address',
        password: 'Password',
        logInTitle: 'Log in to your account',
    },
    hero: {
        badge: 'Built for postgraduate and undergraduate medical exams',
        title: 'Revise smarter. Walk into the exam knowing your score.',
        body: '[YOUR COPY HERE] Describe in two sentences what students get: a question bank built from the real syllabus, timed mock exams, and a score history that shows exactly where the gaps are.',
        primary: 'Find your exam',
        secondary: 'Try a free demo',
    },
    exams: {
        heading: 'Choose your exam',
        intro: 'Pick the exam you are sitting. Every exam has its own question bank, past papers and mock exams.',
        demo: 'Take a demo',
        signUp: 'Sign up',
        questions: (count: number): string => `${count}+ questions`,
        empty: 'Exams are being prepared. Check back shortly.',
    },
    features: {
        textbook: {
            title: 'A textbook that follows the syllabus',
            body: '[YOUR COPY HERE] Explain that the reading material is organised by the topics of each exam, so every paragraph maps to a question you can practise immediately.',
        },
        build: {
            title: 'Build a test from exactly what you need',
            body: '[YOUR COPY HERE] Explain that a student can choose a category, a past paper or a set of questions, set the time, and sit the test straight away.',
        },
        compare: {
            title: 'Am I on track? See how you compare',
            body: '[YOUR COPY HERE] Explain that the score history compares every mock with the candidates who sat the same paper, so the student knows whether the result is competitive.',
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
        intro: 'One price per Qbank. Each Qbank includes its main MCQ bank and every past paper MCQ.',
        choose: 'Subscribe',
        member: 'Already a member? Log in to renew',
        placeholderPrice: '[PRICE]',
        month: '1 month',
        quarter: '3 months',
        year: '12 months',
        popular: '3 months · most popular',
        empty: 'Plans are being set up. Check back shortly.',
    },
    footer: {
        terms: 'Terms',
        privacy: 'Privacy',
        contact: 'Contact',
        cookies: 'Cookie settings',
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
        'MRCP World is a question bank, textbook and mock exam platform for medical students. Practise the real syllabus, sit timed mock exams and track your score against other candidates.',
    ogTitle: 'MRCP World — medical exam question bank and mock exams',
    ogDescription: 'Question banks, timed mock exams, past papers and score history for medical students.',
    ogType: 'website',
};
