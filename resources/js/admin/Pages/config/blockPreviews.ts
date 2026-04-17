import { defineAsyncComponent  } from 'vue';
import type {Component} from 'vue';
import GenericBlockPreview from '../partials/GenericBlockPreview.vue';

const previewModules = import.meta.glob('/blocks/*/Preview.vue');

const cache = new Map<string, Component>();

export function getBlockPreview(type: string): Component | null {
    if (cache.has(type)) {
return cache.get(type)!;
}

    const key = `/blocks/${type}/Preview.vue`;

    if (!previewModules[key]) {
        cache.set(type, GenericBlockPreview);

        return GenericBlockPreview;
    }

    const component = defineAsyncComponent(previewModules[key] as () => Promise<unknown>);
    cache.set(type, component);

    return component;
}
