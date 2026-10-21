<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Public landing page
    |--------------------------------------------------------------------------
    |
    | The landing page shows the score distribution of the candidates who
    | finished a mock exam. When fewer real candidates than
    | "minimum_candidates" are recorded, the sample distribution below is
    | shown instead and clearly labelled as a sample.
    |
    */

    'minimum_candidates' => 50,

    /*
    |--------------------------------------------------------------------------
    | Sample distribution
    |--------------------------------------------------------------------------
    |
    | Twenty buckets of five percent each, from 0-5 up to 95-100. These are
    | placeholder numbers for the public page and are never mixed with the
    | recorded candidates.
    |
    */

    'sample_distribution' => [2, 3, 5, 8, 12, 17, 23, 29, 34, 38, 41, 42, 40, 37, 32, 26, 20, 14, 9, 5],
];
