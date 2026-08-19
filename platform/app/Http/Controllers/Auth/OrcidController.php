<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Academic\Orcid;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Linking an ORCID iD to an account.
 *
 * This is a *link*, not a sign-in method. An ORCID iD asserts "this researcher
 * is who they say they are" and is displayed as such on exports and deposits;
 * letting it also create accounts would make an identity claim into an
 * authentication path, which is a much larger surface for one badge.
 */
class OrcidController extends Controller
{
    private const STATE_KEY = 'orcid_state';

    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(Orcid::isConfigured(), 503, 'ORCID is not configured.');

        // CSRF for the OAuth round trip: the callback is a GET from a third
        // party, so the session-bound state parameter is the only thing tying
        // the response to a request this user actually started.
        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away(Orcid::authorizeUrl($state, route('orcid.callback')));
    }

    public function callback(Request $request): RedirectResponse
    {
        abort_unless(Orcid::isConfigured(), 503);

        $expected = $request->session()->pull(self::STATE_KEY);

        if (! $expected || ! is_string($request->query('state')) || ! hash_equals($expected, $request->query('state'))) {
            return to_route('profile.edit')->with('error', 'That ORCID link did not complete. Please try again.');
        }

        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return to_route('profile.edit')->with('error', 'ORCID did not return an authorization code.');
        }

        $result = Orcid::exchange($code, route('orcid.callback'));

        if ($result === null) {
            return to_route('profile.edit')->with('error', 'ORCID could not confirm that identity.');
        }

        // One account per iD. Silently reassigning it would let a second
        // account quietly take over a researcher's identity here.
        $taken = User::where('orcid_id', $result['orcid'])
            ->whereKeyNot($request->user()->id)
            ->exists();

        if ($taken) {
            return to_route('profile.edit')->with('error', 'That ORCID iD is already linked to another account.');
        }

        /*
         * forceFill rather than adding these to $fillable.
         *
         * An ORCID iD is an identity claim, and the only legitimate writer of
         * it is this callback, holding a response from ORCID itself. Making it
         * mass-assignable would mean any future `User::update($request->all())`
         * silently accepts a self-declared researcher identity.
         */
        $request->user()->forceFill([
            'orcid_id' => $result['orcid'],
            'orcid_name' => $result['name'],
            'orcid_linked_at' => now(),
        ])->save();

        return to_route('profile.edit')->with('success', 'ORCID iD '.$result['orcid'].' linked.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->user()->forceFill([
            'orcid_id' => null,
            'orcid_name' => null,
            'orcid_linked_at' => null,
        ])->save();

        return back()->with('success', 'ORCID iD unlinked.');
    }
}
