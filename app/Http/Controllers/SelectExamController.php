<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SelectExamController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate(['exam_id' => ['required', 'integer', Rule::exists('exams', 'id')->where('is_active', true)]]);
        $request->session()->put('exam_id', (int) $data['exam_id']);

        return back();
    }
}
