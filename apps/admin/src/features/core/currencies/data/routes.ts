export class FeatureRoutes {
  // Matches the shared ['currencies'] query key used by the settings page —
  // mutations must invalidate every consumer of that list.
  public static readonly CACHE_KEY = 'currencies'
}

export class Locales {
  public static readonly SHARED_COMMON = 'shared/common'
  public static readonly SHARED_DATA_TABLE = 'shared/data-table'
  public static readonly CURRENCY = 'core/currency'
}
