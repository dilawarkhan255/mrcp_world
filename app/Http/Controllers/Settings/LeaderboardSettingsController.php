<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Opt in to the leaderboard with a display name. The name is all other
 * students see; an email address is refused so it cannot be shown by mistake.
 */
class LeaderboardSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'leaderboard_opt_in' => ['required', 'boolean'],
            'leaderboard_name' => [
                Rule::requiredIf((bool) $request->boolean('leaderboard_opt_in')), 'nullable', 'string', 'min:3', 'max:30',
                'regex:/^[\pL\pN][\pL\pN .\-_]*$/u', 'not_regex:/@/',
            ],
        ], [
            'leaderboard_name.regex' => 'Use letters, numbers, spaces, dots, dashes and underscores only.',
            'leaderboard_name.not_regex' => 'Choose a display name, not an email address.',
        ]);
        /** @var User $user */
        $user = $request->user();
        $name = isset($data['leaderboard_name']) ? trim(preg_replace('/\s+/', ' ', (string) $data['leaderboard_name']) ?? '') : null;
        $user->update([
            'leaderboard_opt_in' => (bool) $data['leaderboard_opt_in'],
            'leaderboard_name' => $name === '' ? null : $name,
        ]);

        return back()->with('status', $user->leaderboard_opt_in
            ? 'You are on the leaderboard as '.$user->leaderboard_name.'. Rankings refresh every few minutes.'
            : 'You are no longer shown on the leaderboard.');
    }
}
