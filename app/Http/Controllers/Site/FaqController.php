<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\View\View;

class FaqController extends Controller
{
    public function index(): View
    {
        $faqs = Faq::query()->visible()->get()->groupBy(fn (Faq $faq) => $faq->topic ?: 'General');

        return view('site.faqs', ['groups' => $faqs]);
    }
}
