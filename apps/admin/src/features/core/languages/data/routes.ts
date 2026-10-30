export class FeatureRoutes {
  // Matches the shared ['languages'] query key used by every translation
  // tab — mutations must invalidate every consumer of that list.
  public static readonly CACHE_KEY = 'languages'
}

export class Locales {
  public static readonly SHARED_COMMON = 'shared/common'
  public static readonly SHARED_DATA_TABLE = 'shared/data-table'
  public static readonly LANGUAGE = 'core/language'
}
