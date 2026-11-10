<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [
            ['loc' => route('home'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => route('home').'#pricing', 'changefreq' => 'monthly', 'priority' => '0.8'],
        ];

        foreach (Exam::where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(['slug']) as $exam) {
            $urls[] = ['loc' => route('landing.demo', $exam->slug), 'changefreq' => 'monthly', 'priority' => '0.7'];
        }

        $urls[] = ['loc' => route('legal.terms'), 'changefreq' => 'yearly', 'priority' => '0.3'];
        $urls[] = ['loc' => route('legal.privacy'), 'changefreq' => 'yearly', 'priority' => '0.3'];
        $urls[] = ['loc' => route('contact'), 'changefreq' => 'yearly', 'priority' => '0.3'];
        $urls[] = ['loc' => route('cookie-settings'), 'changefreq' => 'yearly', 'priority' => '0.2'];

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
