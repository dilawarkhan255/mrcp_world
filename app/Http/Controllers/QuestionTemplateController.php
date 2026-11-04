<?php

namespace App\Http\Controllers;

use App\Jobs\ImportQuestions;
use App\Models\Question;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionTemplateController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        Gate::authorize('create', Question::class);

        return response()->streamDownload(function () {
            echo implode(',', ImportQuestions::HEADERS)."\n";
        }, 'mcq-import-template.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
