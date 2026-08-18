<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Support\PostPresenter;
use App\Support\Seo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('categories/index', [
            'categories' => Category::withCount(['posts as posts_count' => fn ($q) => $q->where('status', 'published')])
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Category $category) => $category->preview()),
        ])->withViewData(['seo' => Seo::forPage(
            'Browse by subject',
            'Writing by subject — technology, science, nature, travel, politics, craft and more.',
            route('categories.index'),
        )]);
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
        ])->withViewData(['seo' => Seo::forPage(
            $category->name,
            $category->description ?? "Writing about {$category->name}.",
            route('categories.show', $category),
        )]);
    }
}
