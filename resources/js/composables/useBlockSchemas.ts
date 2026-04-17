import { computed } from 'vue';
import { blockSchemas  } from '@/admin/Pages/config/blockSchemas';
import type {BlockSchema} from '@/admin/Pages/config/blockSchemas';

export interface AvailableBlock {
    type: string;
    label: string;
    // Optional schema fields provided by plugins via block.json / blocks.available hook
    description?: string;
    group?: string;
    icon?: string;
    fields?: BlockSchema['fields'];
    defaultData?: Record<string, unknown>;
}

/**
 * Merges the static compile-time blockSchemas with any dynamic schemas
 * provided by plugins (carried on the availableBlocks prop from the server).
 * Static schemas always win for core blocks; plugin blocks use their own schema.
 */
export function useBlockSchemas(availableBlocks: AvailableBlock[] | (() => AvailableBlock[])) {
    const blocks = typeof availableBlocks === 'function' ? availableBlocks : () => availableBlocks;

    const merged = computed((): Record<string, BlockSchema> => {
        const result: Record<string, BlockSchema> = { ...blockSchemas };

        for (const block of blocks()) {
            if (result[block.type]) {
continue;
} // core schema takes precedence

            if (block.group && block.fields) {
                result[block.type] = {
                    label:       block.label,
                    description: block.description,
                    group:       block.group,
                    icon:        block.icon ?? 'Square',
                    fields:      block.fields,
                };
            }
        }

        return result;
    });

    function getSchema(type: string): BlockSchema | undefined {
        return merged.value[type];
    }

    return { schemas: merged, getSchema };
}
