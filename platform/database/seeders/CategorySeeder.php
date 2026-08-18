<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Subject taxonomy, orthogonal to universes.
 *
 * A universe is *how a piece feels*; a category is *what it is about*. Keeping
 * them separate means a technical essay can be written in Abyss without the
 * taxonomy fighting the mood.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['slug' => 'technology', 'name' => 'Technology', 'icon' => 'Cpu', 'sort_order' => 1,
                'description' => 'Software, systems, and the tools we build to think with.'],
            ['slug' => 'science', 'name' => 'Science', 'icon' => 'Atom', 'sort_order' => 2,
                'description' => 'Observation, evidence, and the slow correction of being wrong.'],
            ['slug' => 'nature-environment', 'name' => 'Nature & Environment', 'icon' => 'Leaf', 'sort_order' => 3,
                'description' => 'The living world, at every scale from moss to climate.'],
            ['slug' => 'travel', 'name' => 'Travel', 'icon' => 'Compass', 'sort_order' => 4,
                'description' => 'Places, and what being somewhere else does to you.'],
            ['slug' => 'politics-society', 'name' => 'Politics & Society', 'icon' => 'Landmark', 'sort_order' => 5,
                'description' => 'Power, institutions, and how people arrange themselves.'],
            ['slug' => 'craft-writing', 'name' => 'Craft & Writing', 'icon' => 'PenTool', 'sort_order' => 6,
                'description' => 'The work of putting sentences in order.'],
            ['slug' => 'personal-essay', 'name' => 'Personal Essay', 'icon' => 'Heart', 'sort_order' => 7,
                'description' => 'First person, and earned rather than confessional.'],
            ['slug' => 'culture-arts', 'name' => 'Culture & Arts', 'icon' => 'Palette', 'sort_order' => 8,
                'description' => 'Books, film, music, and the arguments about them.'],
            ['slug' => 'philosophy', 'name' => 'Philosophy', 'icon' => 'BrainCircuit', 'sort_order' => 9,
                'description' => 'Questions that stay open, examined carefully.'],
            ['slug' => 'tutorials', 'name' => 'Tutorials', 'icon' => 'GraduationCap', 'sort_order' => 10,
                'description' => 'Step-by-step, tested, and written to be followed.'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
