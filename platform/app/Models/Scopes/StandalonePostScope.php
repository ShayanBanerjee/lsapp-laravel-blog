<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Hides course lessons from every query that has not explicitly asked for them.
 *
 * Lessons are Posts (see the courses migration for why), which means every
 * existing feed, listing, sitemap, RSS document, search and profile page would
 * otherwise start including fragments of courses out of context.
 *
 * A global scope is used rather than a `->articles()` scope applied at each
 * call site because the two failure modes are not symmetrical: forgetting to
 * apply a filter publishes something that should not have been public, while
 * forgetting to remove one merely hides something. Defaulting to hidden means
 * the mistake anyone actually makes is the harmless one.
 *
 * To read lessons, opt out explicitly:
 *
 *     Post::withoutGlobalScope(StandalonePostScope::class)
 *
 * CourseModule::lessons() already does this, so ordinary course code never
 * needs to think about it.
 */
class StandalonePostScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereNull($model->qualifyColumn('course_module_id'));
    }
}
