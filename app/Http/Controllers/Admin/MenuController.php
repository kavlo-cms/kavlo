<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Theme;
use App\Services\ContentRouteRegistry;
use App\Services\ThemeManifest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    public function index(): Response
    {
        $menus = Menu::withCount('items')->latest()->get();

        return Inertia::render('Menus/Index', compact('menus'));
    }

    public function create(): Response
    {
        return Inertia::render('Menus/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:menus,slug',
        ]);

        $menu = Menu::create($data);
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.menus.edit', $menu)->with('success', 'Menu created.');
    }

    public function edit(Menu $menu): Response
    {
        $pages = Page::select('id', 'title', 'slug')->orderBy('title')->get();
        $themeSlug = Theme::where('is_active', true)->value('slug') ?? 'blocks';
        $themeConfig = $this->readThemeConfig($themeSlug);

        return Inertia::render('Menus/Edit', [
            'menu' => $menu,
            'items' => $this->buildTree($menu->id),
            'pages' => $pages,
            'themeConfig' => $themeConfig,
        ]);
    }

    public function update(Request $request, Menu $menu): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:255|unique:menus,slug,'.$menu->id,
            'items' => 'present|array',
        ]);

        $menu->update($request->only('name', 'slug'));
        $this->syncItems($menu->id, $request->items, null);

        app(ContentRouteRegistry::class)->forget();

        return back()->with('success', 'Menu saved.');
    }

    public function destroy(Menu $menu): RedirectResponse
    {
        $menu->delete();
        app(ContentRouteRegistry::class)->forget();

        return redirect()->route('admin.menus.index')->with('success', 'Menu deleted.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function readThemeConfig(string $slug): array
    {
        $path = base_path("themes/{$slug}/theme.json");

        return app(ThemeManifest::class)->loadFromPath($path);
    }

    private function buildTree(int $menuId): array
    {
        $flat = MenuItem::where('menu_id', $menuId)
            ->orderBy('order')
            ->get()
            ->toArray();

        return $this->nestItems($flat, null);
    }

    private function nestItems(array $items, ?int $parentId): array
    {
        $result = [];

        foreach ($items as $item) {
            if ($item['parent_id'] === $parentId) {
                $item['children'] = $this->nestItems($items, $item['id']);
                $result[] = $item;
            }
        }

        return $result;
    }

    private function syncItems(int $menuId, array $items, ?int $parentId, int &$order = 0): void
    {
        if ($parentId === null) {
            MenuItem::where('menu_id', $menuId)->delete();
            $order = 0;
        }

        foreach ($items as $itemData) {
            $item = MenuItem::create([
                'menu_id' => $menuId,
                'label' => $itemData['label'] ?? 'Item',
                'url' => $itemData['url'] ?? null,
                'page_id' => $itemData['page_id'] ?? null,
                'target' => $itemData['target'] ?? '_self',
                'parent_id' => $parentId,
                'order' => $order++,
            ]);

            if (! empty($itemData['children'])) {
                $childOrder = 0;
                $this->syncItems($menuId, $itemData['children'], $item->id, $childOrder);
            }
        }
    }
}
