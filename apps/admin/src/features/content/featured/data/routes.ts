export class FeatureRoutes {
  // distinct from catalog's 'featured' key — the two curation pages
  // must not share a react-query cache entry
  public static readonly CACHE_KEY = 'featured-posts'
}

export class Locales {
  public static readonly SHARED_COMMON = 'shared/common'
  public static readonly FEATURED = 'content/featured'
}
