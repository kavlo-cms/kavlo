export interface Block {
    id: string; // UUID for drag-and-drop tracking
    type: string; // e.g., 'hero', 'image-grid'
    data: any; // The attributes for that specific block
    order: number;
}

export interface Page {
    id: number;
    title: string;
    slug: string;
    blocks: Block[] | null;
    is_published: boolean;
}
