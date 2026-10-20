<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionTemplateController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        Gate::authorize('create', Question::class);

        return response()->streamDownload(function () {
            echo "code,category_slug,question,option_a,option_b,option_c,option_d,option_e,correct_option,explanation\n";
        }, 'mcq-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
