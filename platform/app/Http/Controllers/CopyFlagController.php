<?php

namespace App\Http\Controllers;

use App\Models\CopyFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CopyFlagController extends Controller
{
    /**
     * Dismiss a flag on your own piece.
     *
     * The author is the reviewer here because there is no moderation staff to
     * be one, and because the overwhelmingly common case is a true positive
     * with an innocent explanation — a quoted passage, a republished draft,
     * their own words under a second persona. Dismissals are kept rather than
     * deleted so a pattern of them stays visible to whoever eventually does
     * review this.
     */
    public function clear(Request $request, CopyFlag $flag): RedirectResponse
    {
        abort_unless(
            $request->user()->posts()->whereKey($flag->post_id)->exists(),
            403,
        );

        $flag->update(['status' => CopyFlag::CLEARED]);

        return back()->with('success', 'Dismissed.');
    }
}
