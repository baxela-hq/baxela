export const StorageKeys = {
  DEFAULT_LANGUAGE: 'DEFAULT_LANGUAGE',
  DEFAULT_CURRENCY: 'DEFAULT_CURRENCY',
} as const;

export type StorageKeysType = (typeof StorageKeys)[keyof typeof StorageKeys];

export class StorageUtility {
  static setItem<T>(key: StorageKeysType, value: T): void {
    try {
      const jsonValue = JSON.stringify(value);
      localStorage.setItem(key, jsonValue);
    } catch (_e) {
      // Silently fail - no-op
    }
  }

  static getItem<T>(key: StorageKeysType): T | null {
    try {
      const jsonValue = localStorage.getItem(key);
      if (jsonValue == null) {
        return null;
      }
      return JSON.parse(jsonValue) as T;
    } catch (_e) {
      return null;
    }
  }

  static removeItem(key: StorageKeysType): void {
    try {
      localStorage.removeItem(key);
    } catch (_e) {
      // Silently fail - no-op
    }
  }

  static clear(): void {
    try {
      localStorage.clear();
    } catch (_error) {
      // Silently fail - no-op
    }
  }

  static getMultipleItems(
    keys: Array<StorageKeysType>,
  ): Record<StorageKeysType, unknown> | undefined {
    try {
      // Using localStorage directly since multiGet is not a standard browser API
      // If you're using React Native or a specific library, you might need to import it
      const result = keys.map((key) => {
        const value = localStorage.getItem(key);
        return [key, value] as [StorageKeysType, string | null];
      });

      const final = result.reduce(
        (pre: Record<StorageKeysType, unknown>, curr: [StorageKeysType, string | null]) => {
          const [key, value] = curr;
          const parsedValue = value != null ? JSON.parse(value) : null;
          return {
            ...pre,
            [key]: parsedValue,
          };
        },
        {} as Record<StorageKeysType, unknown>,
      );
      return final;
    } catch (_err) {
      return undefined;
    }
  }
}
