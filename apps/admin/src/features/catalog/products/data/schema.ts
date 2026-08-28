import { z } from 'zod'
import type { Language } from '@/shared/types/locale.types'
import { getDefaultCurrency } from '@/shared/lib/locale.ts'
import { categorySchema } from '../../categories/data/schema';
import { DATA_TYPES } from '../../attributes/data/schema';



export const STATUSES = [
  'discontinued',
  'out_of_stock',
  'in_stock',
  'inactive',
];

export const TYPES = ['simple', 'variable']

export const IMAGE_COLLECTION = 'photos';

export const productImageSchema = z.object({
  position: z.number().min(1),
  collection: z.string(),
  media_id: z.number(),
  url: z.string(),
});
export type ProductImage = z.infer<typeof productImageSchema>

export const variantOptionRefSchema = z.object({
  id: z.number(),
  option_id: z.number(),
});

const variantSchema = z.object({
  sku: z.string().min(1, "SKU cannot be empty"),
  quantity: z.number().min(0, "Quantity cannot be negative"), 
  price: z.string().refine((value) => /^\d+\.\d{2}$/.test(value), {
    message: 'The format must include two decimal numbers like 100.99',
  }),
  is_default: z.boolean(),
  currency_id: z.coerce.number().nullish(),
  option_value_ids: z.array(z.number()).optional(),
  optionValues: z.array(variantOptionRefSchema).optional(),
});
export type VariantForm = z.infer<typeof variantSchema>


export const translationSchema = z.object({
  language_id: z.number(),
  language: z.string(),
  title: z.string().min(1, 'required'),
  slug: z.string().min(1, 'required'),
  description: z.string().nullable(),
  content: z.string().nullable(),
});
export type TranslationForm = z.infer<typeof translationSchema>

// Rows returned by the product endpoints under `attributeValues` — the nested
// attribute is eager-loaded without translations/group/values.
const productAttributeRowSchema = z.object({
  id: z.number(),
  attribute_id: z.number(),
  attribute_value_id: z.number().nullable(),
  text_value: z.string().nullable(),
  // decimal:2 column — the API sends/receives it as a string ("6.70")
  number_value: z.union([z.string(), z.number()]).nullable(),
  boolean_value: z.boolean().nullable(),
  attribute: z
    .object({
      id: z.number(),
      group_id: z.number(),
      code: z.string(),
      data_type: z.enum(DATA_TYPES),
      is_filterable: z.boolean(),
      position: z.string(),
    })
    .nullish(),
});
type ProductAttributeRow = z.infer<typeof productAttributeRowSchema>

const _productSchema = z.object({
  id: z.number(),
  translations: z.array(translationSchema),
  type: z.string(),
  status: z.string(),
  is_published: z.boolean(),
  categories: z.array(categorySchema),
  variants: z.array(variantSchema),
  images: z.array(productImageSchema).optional(),
  attributeValues: z.array(productAttributeRowSchema).optional(),
  created_at: z.string(),
  updated_at: z.string(),
})

export type Product = z.infer<typeof _productSchema>

// One UI entry per selected attribute — `data_type` is denormalized so the
// submit serializer never needs to look attributes up. Serialized to the flat
// API rows via serializeAttributeValues (multiselect = repeated rows).
const productAttributeValueFormSchema = z.object({
  attribute_id: z.number(),
  data_type: z.enum(DATA_TYPES),
  value_ids: z.array(z.number()),
  text_value: z.string().max(255).nullable(),
  number_value: z.string().nullable(),
  boolean_value: z.boolean().nullable(),
});
export type ProductAttributeValueEntry = z.infer<typeof productAttributeValueFormSchema>

export const formSchema = z.object({
  type: z.enum(TYPES),
  status: z.enum(STATUSES),
  is_published: z.boolean(),
  categories: z.array(z.number()).refine((value: number[]) => value.some((item: number) => item), {
    message: "You have to select at least one item.",
  }),
  variants: z.array(variantSchema),
  images: z.array(productImageSchema),
  attribute_values: z.array(productAttributeValueFormSchema),
  translations: z.array(translationSchema),
}).superRefine((data, ctx) => {
  if (data.type === 'variable') {
    data.variants.forEach((variant, index) => {
      if (!variant.option_value_ids || variant.option_value_ids.length === 0) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['variants', index, 'option_value_ids'],
          message: 'Select at least one option value for each variant of a variable product.',
        });
      }
    });
  }
  data.attribute_values.forEach((entry, index) => {
    if (
      entry.data_type === 'number' &&
      entry.number_value &&
      !/^\d{1,12}(\.\d{1,2})?$/.test(entry.number_value)
    ) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['attribute_values', index, 'number_value'],
        message: 'The number can have up to 12 digits and 2 decimals like 99.99',
      });
    }
  });
})
type ProductForm = z.infer<typeof formSchema>

// The flat rows the create/update endpoints expect: exactly one filled value
// slot per row; multiselect attributes are repeated rows sharing attribute_id.
type ProductAttributeValuePayload = {
  attribute_id: number;
  attribute_value_id: number | null;
  text_value: string | null;
  number_value: string | null;
  boolean_value: boolean | null;
};

export type ProductPayload = Omit<ProductForm, 'attribute_values'> & {
  attribute_values: ProductAttributeValuePayload[];
};

// The backend rejects rows whose typed slot is empty, so unfilled entries are
// dropped — they only exist as unsaved UI state. Booleans always count as set
// (false included).
export function serializeAttributeValues(
  entries: ProductAttributeValueEntry[]
): ProductAttributeValuePayload[] {
  const rows: ProductAttributeValuePayload[] = [];
  entries.forEach((entry) => {
    const base: ProductAttributeValuePayload = {
      attribute_id: entry.attribute_id,
      attribute_value_id: null,
      text_value: null,
      number_value: null,
      boolean_value: null,
    };
    if (entry.data_type === 'select') {
      const valueId = entry.value_ids[0];
      if (valueId) rows.push({ ...base, attribute_value_id: valueId });
    } else if (entry.data_type === 'multiselect') {
      entry.value_ids.forEach((valueId) => rows.push({ ...base, attribute_value_id: valueId }));
    } else if (entry.data_type === 'text') {
      if (entry.text_value?.trim()) rows.push({ ...base, text_value: entry.text_value });
    } else if (entry.data_type === 'number') {
      if (entry.number_value?.trim()) rows.push({ ...base, number_value: entry.number_value });
    } else {
      rows.push({ ...base, boolean_value: entry.boolean_value ?? false });
    }
  });
  return rows;
}

function buildAttributeValueEntries(rows: ProductAttributeRow[]): ProductAttributeValueEntry[] {
  const byAttribute = new Map<number, ProductAttributeRow[]>();
  rows.forEach((row) => {
    const group = byAttribute.get(row.attribute_id) ?? [];
    group.push(row);
    byAttribute.set(row.attribute_id, group);
  });

  return Array.from(byAttribute.entries()).map(([attributeId, attributeRows]) => {
    const dataType = attributeRows[0].attribute?.data_type ?? 'text';
    const valueIds = attributeRows
      .map((row) => row.attribute_value_id)
      .filter((valueId): valueId is number => valueId !== null);

    return {
      attribute_id: attributeId,
      data_type: dataType,
      value_ids: dataType === 'multiselect' ? valueIds : valueIds.slice(0, 1),
      text_value: attributeRows.find((row) => row.text_value !== null)?.text_value ?? null,
      number_value:
        attributeRows.find((row) => row.number_value !== null)?.number_value?.toString() ?? null,
      boolean_value: dataType === 'boolean' ? (attributeRows[0].boolean_value ?? false) : null,
    };
  });
}

const _productStatusSchema = z.union([
  z.literal('discontinued'),
  z.literal('out_of_stock'),
  z.literal('in_stock'),
  z.literal('inactive'),
])
export type ProductStatus = z.infer<typeof _productStatusSchema>


export const defaultValues: ProductForm = {
  type: 'simple',
  status: '',
  is_published: false,
  categories: [],
  variants: [{
    sku: "", price: "", is_default: false, quantity: 0, currency_id: getDefaultCurrency()?.id ?? null, option_value_ids: [],
  }],
  images: [] as ProductImage[],
  attribute_values: [] as ProductAttributeValueEntry[],
  translations: [] as TranslationForm[],
}

export function buildDefaultValues(languages: Language[]): ProductForm {
  return {
    type: 'simple',
    status: '',
    is_published: false,
    categories: [],
    variants: [{
      sku: '', price: '', is_default: false, quantity: 0, currency_id: getDefaultCurrency()?.id ?? null, option_value_ids: [],
    }],
    images: [],
    attribute_values: [],
    translations: languages.map((language, index) => ({
      language_id: index,
      language: language.code,
      title: '',
      slug: '',
      description: '',
      content: '',
    })),
  }
}

export function buildEditValues(
  languages: Language[],
  currentRow?: Product
): ProductForm {
  const base = buildDefaultValues(languages)
  if (!currentRow) return base


  const translationsMap = new Map(
    currentRow.translations.map((t) => {
      return [t.language, t];
    })
  )

  return {
    type: currentRow.type,
    status: currentRow.status,
    is_published: currentRow.is_published,
    categories: currentRow.categories.map((cat) => cat.id),
    variants: currentRow.variants.map((va) => ({
      sku: va.sku,
      price: va.price,
      is_default: va.is_default,
      quantity: va.quantity,
      currency_id: getDefaultCurrency()?.id ?? null,
      option_value_ids: va.option_value_ids || [],
    })),
    images: [...(currentRow.images ?? [])]
      .sort((a, b) => a.position - b.position)
      .map((image, index) => ({
        position: index + 1,
        collection: image.collection,
        media_id: image.media_id,
        url: image.url,
      })),
    attribute_values: buildAttributeValueEntries(currentRow.attributeValues ?? []),
    translations: base.translations.map((baseTranslation, index) => {
      const existing = translationsMap.get(baseTranslation.language)
      return existing
        ? { ...baseTranslation, ...existing }
        : { ...baseTranslation, language_id: index }
    }),
  }
}


