<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Highlight;
use App\Models\Post;
use App\Support\PostPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = Category::withCount(['posts as posts_count' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('sort_order')
            ->get();

        /*
         * The most-marked passage in each subject.
         *
         * A subject hub listing counts tells you how big a room is; a sentence
         * somebody stopped on tells you what is being said in it. One grouped
         * query for all subjects rather than one per subject.
         */
        $signals = Highlight::query()
            ->join('category_post', 'category_post.post_id', '=', 'highlights.post_id')
            ->join('posts', 'posts.id', '=', 'highlights.post_id')
            ->where('posts.status', 'published')
            ->groupBy('category_post.category_id', 'highlights.quote')
            ->selectRaw('category_post.category_id, highlights.quote, COUNT(*) as marks')
            ->orderByDesc('marks')
            ->get()
            ->unique('category_id')
            ->keyBy('category_id');

        return Inertia::render('categories/index', [
            'categories' => $categories->map(fn (Category $category) => [
                ...$category->preview(),
                'signal' => $signals->get($category->id)
                    ? [
                        'quote' => $signals[$category->id]->quote,
                        'marks' => (int) $signals[$category->id]->marks,
                    ]
                    : null,
            ]),
        ]);
    }

    public function show(Request $request, Category $category): Response
    {
        $posts = $category->posts()
            ->published()
            ->with(['persona', 'universe', 'categories'])
            ->withCount('highlights')
            ->latest('published_at')
            ->paginate(12)
            ->through(fn (Post $post) => PostPresenter::card($post));

        return Inertia::render('categories/show', [
            'category' => $category->loadCount(['posts as posts_count' => fn ($q) => $q->where('status', 'published')])->preview(),
            'posts' => $posts,
        ]);
    }
}
