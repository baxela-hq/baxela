export type TreeItem = {
  id: number;
  parent_id: number | null;
  title?: string;
};

export type TreeNode<T> = T & { title: string; depth: number };

export function buildHierarchy<T extends TreeItem>(
  items: T[],
  getTitle: (item: T) => string = (item) => item.title ?? '',
): TreeNode<T>[] {
  // Group children by parent_id
  const map = new Map<number | null, T[]>();
  items.forEach(item => {
    const parentId = item.parent_id ?? null;
    const group = map.get(parentId);
    if (group) {
      group.push(item);
    } else {
      map.set(parentId, [item]);
    }
  });

  // Recursive function to build ordered list (parents first, then their children)
  function traverse(parentId: number | null, depth: number, result: TreeNode<T>[]) {
    const children = map.get(parentId) || [];
    // Sort children by id (or created_at if you prefer chronological order)
    children.sort((a, b) => a.id - b.id);

    for (const child of children) {
      // Clone item with the display title and its depth for indentation
      result.push({ ...child, title: getTitle(child), depth });

      // Recurse into children
      traverse(child.id, depth + 1, result);
    }
  }

  const result: TreeNode<T>[] = [];
  traverse(null, 0, result); // Start from root (parent_id = null)

  return result;
}
