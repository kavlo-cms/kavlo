<?php

namespace App\Services;

use App\Models\Menu;
use App\Models\MenuItem;
use Illuminate\Support\Collection;

class MenuWalker
{
    protected array $format = [
        'container_tag'   => 'nav', // Better for SEO than <ul>
        'container_class' => 'main-navigation',
        'list_tag'        => 'ul',
        'item_tag'        => 'li',
        'item_class'      => 'menu-item',
        'link_class'      => 'menu-link',
    ];

    public function render(Menu $menu, array $options = []): string
    {
        $this->format = array_merge($this->format, $options);

        $container = $this->format['container_tag'];
        $class = $this->format['container_class'];

        // Wrap the whole thing in a <nav> with Schema attributes
        $html = "<{$container} class=\"{$class}\" role=\"navigation\" itemscope=\"itemscope\" itemtype=\"https://schema.org/SiteNavigationElement\">";
        $html .= $this->walk($menu->items);
        $html .= "</{$container}>";

        return $html;
    }

    protected function walk(Collection $items, int $depth = 0): string
    {
        $tag = $this->format['list_tag'];
        $class = $depth === 0 ? 'nav-list' : 'sub-menu';

        $html = "<{$tag} class=\"{$class}\">";

        foreach ($items as $item) {
            $html .= $this->renderItem($item, $depth);
        }

        $html .= "</{$tag}>";
        return $html;
    }

    protected function renderItem(MenuItem $item, int $depth): string
    {
        $itemTag = $this->format['item_tag'];
        $url = $item->page_id
            ? ($item->page->is_homepage ? url('/') : url($item->page->slug))
            : $item->url;

        $active = $item->isActive() ? ' current-menu-item' : '';
        $hasChildren = $item->children->isNotEmpty() ? ' has-children' : '';

        // LI: Schema 'name' and 'url' are usually expected inside the list item context
        $html = "<{$itemTag} class=\"{$this->format['item_class']}{$active}{$hasChildren}\" itemprop=\"name\">";

        // A: Explicitly tell Google this is the URL for the navigation element
        $html .= "<a href=\"{$url}\" class=\"{$this->format['link_class']}\" itemprop=\"url\">" . e($item->label) . "</a>";

        if ($item->children->isNotEmpty()) {
            $html .= $this->walk($item->children, $depth + 1);
        }

        $html .= "</{$itemTag}>";

        return $html;
    }
}
