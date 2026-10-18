<?php

namespace App\Modules\Platform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Platform\Services\AiAssistantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Halaman Asisten AI (F5.1) — owner-only (ditegakkan service),
 * read-only. Setiap jawaban dirender bersama dasar datanya.
 */
class AssistantController extends Controller
{
    public function index(Request $request): View
    {
        if (! $request->user()->hasRole('Owner')) {
            abort(403);
        }

        return view('assistant.index', ['result' => session('ai_result'), 'question' => session('ai_question')]);
    }

    public function ask(Request $request, AiAssistantService $assistant): RedirectResponse
    {
        $validated = $request->validate(['question' => ['required', 'string', 'max:500']]);
        $result = $assistant->ask($request->user(), $validated['question']);

        return redirect()->route('assistant.index')
            ->with('ai_result', $result)
            ->with('ai_question', $validated['question']);
    }
}
