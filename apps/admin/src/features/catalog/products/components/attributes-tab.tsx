import { useMemo, useState } from 'react';
import { type z } from 'zod';
import { type Control, useFieldArray, useWatch } from 'react-hook-form';
import { useQuery } from '@tanstack/react-query';
import { Link } from '@tanstack/react-router';
import { LoaderIcon, XIcon } from 'lucide-react';
import { toast } from 'sonner';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import { ApiError } from '@/shared/lib/api-error';
import { parseAndToastError } from '@/shared/lib/utils';
import { getDefaultLanguage } from '@/shared/lib/locale';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { fetchAttributes } from '@/features/catalog/attributes/api/attributes.api';
import { Locales as AttributeLocales } from '@/features/catalog/attributes/data/routes';
import { dataTypeColors } from '@/features/catalog/attributes/data/data';
import { type Attribute } from '@/features/catalog/attributes/data/schema';
import { fetchAttributeGroups } from '@/features/catalog/attribute-groups/api/attribute-groups.api';
import { type AttributeGroup } from '@/features/catalog/attribute-groups/data/schema';
import { fetchAttributeValues } from '@/features/catalog/attribute-values/api/attribute-values.api';
import { type AttributeValue } from '@/features/catalog/attribute-values/data/schema';
import {
  fetchAttributeTemplates,
  fetchOneAttributeTemplate,
} from '@/features/catalog/attribute-templates/api/attribute-templates.api';
import { Locales } from '../data/routes';
import { type formSchema, type ProductAttributeValueEntry } from '../data/schema';

type AttributesControl = Control<
  z.input<typeof formSchema>,
  undefined,
  z.output<typeof formSchema>
>;

type ProductAttributesTabProps = {
  control: AttributesControl;
};

type AttributeValuesControlProps = {
  control: AttributesControl;
  index: number;
  attributeId: number;
  multiselect: boolean;
};

function attributeTitle(attribute: Attribute) {
  const index = getDefaultLanguage(attribute.translations);
  return attribute.translations[index ?? 0]?.title ?? attribute.code;
}

function attributeValueTitle(value: AttributeValue) {
  const index = getDefaultLanguage(value.translations);
  return value.translations[index ?? 0]?.title ?? `#${value.id}`;
}

function attributeGroupTitle(group: AttributeGroup) {
  const index = getDefaultLanguage(group.translations);
  return group.translations[index ?? 0]?.title ?? `#${group.id}`;
}

function byPosition(a?: Attribute, b?: Attribute) {
  if (!a || !b) return 0;
  return Number(a.position) - Number(b.position) || a.id - b.id;
}

// Select/multiselect attributes pick from the attribute's predefined values,
// fetched per attribute (cached by react-query under ['attribute-values', id]).
function AttributeValueField({
  control,
  index,
  attributeId,
  multiselect,
}: AttributeValuesControlProps) {
  const { tLabel } = useAppTranslation(Locales.PRODUCT);
  const { tPlaceHolder, tMessage } = useAppTranslation(Locales.SHARED_COMMON);

  const { data, isLoading } = useQuery({
    queryKey: ['attribute-values', attributeId],
    queryFn: () => fetchAttributeValues(String(attributeId), { per_page: 1000 }),
  });
  const values = data?.data ?? [];

  if (isLoading) {
    return (
      <div className='flex h-9 items-center gap-2 text-sm text-muted-foreground'>
        <LoaderIcon size={14} className='animate-spin' />
        {tMessage('info.loading')}
      </div>
    );
  }

  if (values.length === 0) {
    return (
      <p className='text-sm text-muted-foreground'>
        {tLabel('no_attribute_values')}{' '}
        <Link
          className='underline'
          to='/catalog/attributes/values/$id'
          params={{ id: String(attributeId) }}
        >
          {tLabel('manage_values')}
        </Link>
      </p>
    );
  }

  if (!multiselect) {
    return (
      <FormField
        control={control}
        name={`attribute_values.${index}.value_ids`}
        render={({ field }) => (
          <FormItem className='m-0'>
            <Select
              value={field.value[0] ? String(field.value[0]) : undefined}
              onValueChange={(value) => field.onChange([Number(value)])}
            >
              <FormControl>
                <SelectTrigger className='w-full'>
                  <SelectValue placeholder={tPlaceHolder('select')} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {values.map((value) => (
                  <SelectItem key={value.id} value={String(value.id)}>
                    {attributeValueTitle(value)}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            <FormMessage />
          </FormItem>
        )}
      />
    );
  }

  return (
    <FormField
      control={control}
      name={`attribute_values.${index}.value_ids`}
      render={() => (
        <FormItem className='m-0'>
          <div className='max-h-48 space-y-2 overflow-y-auto rounded-md border p-3'>
            {values.map((value) => (
              <FormField
                key={value.id}
                control={control}
                name={`attribute_values.${index}.value_ids`}
                render={({ field }) => (
                  <FormItem
                    key={value.id}
                    className='flex flex-row items-center gap-2 space-y-0'
                  >
                    <FormControl>
                      <Checkbox
                        id={`attribute-value-${index}-${value.id}`}
                        checked={field.value?.includes(value.id)}
                        onCheckedChange={(checked) => {
                          return checked
                            ? field.onChange([...(field.value ?? []), value.id])
                            : field.onChange(
                                field.value?.filter(
                                  (valueId: number) => valueId !== value.id
                                )
                              );
                        }}
                      />
                    </FormControl>
                    <label
                      htmlFor={`attribute-value-${index}-${value.id}`}
                      className='cursor-pointer text-sm'
                    >
                      {attributeValueTitle(value)}
                    </label>
                  </FormItem>
                )}
              />
            ))}
          </div>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

export function ProductAttributesTab({ control }: ProductAttributesTabProps) {
  const { tLabel, tHelpText } = useAppTranslation(Locales.PRODUCT);
  const { tStatus: tAttributeStatus } = useAppTranslation(AttributeLocales.ATTRIBUTE);
  const { tPlaceHolder, tMessage } = useAppTranslation(Locales.SHARED_COMMON);

  const [templateValue, setTemplateValue] = useState('');
  const [templateLoading, setTemplateLoading] = useState(false);
  const [addAttributeValue, setAddAttributeValue] = useState('');

  const { fields, append, remove } = useFieldArray({
    control,
    name: 'attribute_values',
  });
  const watchedEntries = useWatch({ control, name: 'attribute_values' }) ?? [];

  const { data: attributesData, isLoading: attributesLoading } = useQuery({
    queryKey: ['attributes', { per_page: 1000 }],
    queryFn: () => fetchAttributes({ per_page: 1000 }),
  });
  const { data: groupsData } = useQuery({
    queryKey: ['attribute-groups', { per_page: 1000 }],
    queryFn: () => fetchAttributeGroups({ per_page: 1000 }),
  });
  const { data: templatesData } = useQuery({
    queryKey: ['attribute-templates', { per_page: 1000 }],
    queryFn: () => fetchAttributeTemplates({ per_page: 1000 }),
  });

  // Attributes discovered through template details but missing from the (paged)
  // attributes list, so template-loaded rows always render proper labels.
  const [extraAttributes, setExtraAttributes] = useState<Attribute[]>([]);

  const attributes = useMemo(
    () => [...(attributesData?.data ?? []), ...extraAttributes],
    [attributesData, extraAttributes]
  );
  const groups = useMemo(() => groupsData?.data ?? [], [groupsData]);
  const templates = useMemo(
    () => (templatesData?.data ?? []).filter((template) => template.is_active),
    [templatesData]
  );

  const attributeById = useMemo(() => {
    const map = new Map<number, Attribute>();
    attributes.forEach((attribute) => {
      if (!map.has(attribute.id)) map.set(attribute.id, attribute);
    });
    return map;
  }, [attributes]);
  const groupById = useMemo(
    () => new Map(groups.map((group) => [group.id, group])),
    [groups]
  );

  const selectedIds = useMemo(
    () => new Set(fields.map((field) => field.attribute_id)),
    [fields]
  );

  const orderedGroups = useMemo(
    () =>
      groups
        .slice()
        .sort((a, b) => Number(a.position) - Number(b.position) || a.id - b.id),
    [groups]
  );

  // Attributes available in the picker, grouped by their attribute group and
  // ordered by position — same ordering used to lay out the selected rows.
  const pickerGroups = useMemo(() => {
    const known = new Map<number, Attribute[]>();
    const ungrouped: Attribute[] = [];
    attributes.forEach((attribute) => {
      if (selectedIds.has(attribute.id)) return;
      if (groupById.has(attribute.group_id)) {
        const list = known.get(attribute.group_id) ?? [];
        list.push(attribute);
        known.set(attribute.group_id, list);
      } else {
        ungrouped.push(attribute);
      }
    });
    known.forEach((list) => list.sort(byPosition));
    ungrouped.sort(byPosition);
    return { known, ungrouped };
  }, [attributes, groupById, selectedIds]);

  const displaySections = useMemo(() => {
    const rowsByGroup = new Map<number, { index: number; attribute?: Attribute }[]>();
    const ungrouped: { index: number; attribute?: Attribute }[] = [];
    fields.forEach((field, index) => {
      const attribute = attributeById.get(field.attribute_id);
      const row = { index, attribute };
      if (attribute && groupById.has(attribute.group_id)) {
        const list = rowsByGroup.get(attribute.group_id) ?? [];
        list.push(row);
        rowsByGroup.set(attribute.group_id, list);
      } else {
        ungrouped.push(row);
      }
    });

    const sections = orderedGroups
      .map((group) => ({
        key: `group-${group.id}`,
        title: attributeGroupTitle(group),
        rows: (rowsByGroup.get(group.id) ?? []).slice().sort((a, b) => byPosition(a.attribute, b.attribute)),
      }))
      .filter((section) => section.rows.length > 0);

    if (ungrouped.length > 0) {
      sections.push({ key: 'ungrouped', title: tLabel('ungrouped'), rows: ungrouped });
    }
    return sections;
  }, [fields, attributeById, groupById, orderedGroups]); // eslint-disable-line react-hooks/exhaustive-deps

  const availableCount =
    pickerGroups.ungrouped.length +
    Array.from(pickerGroups.known.values()).reduce((sum, list) => sum + list.length, 0);

  const makeEmptyEntry = (attribute: Attribute): ProductAttributeValueEntry => ({
    attribute_id: attribute.id,
    data_type: attribute.data_type,
    value_ids: [],
    text_value: null,
    number_value: null,
    // A boolean switch is always meaningful (off = false), so it starts set.
    boolean_value: attribute.data_type === 'boolean' ? false : null,
  });

  const handleAddAttribute = (value: string) => {
    const attribute = attributeById.get(Number(value));
    if (attribute) append(makeEmptyEntry(attribute));
    setAddAttributeValue('');
  };

  // Loading a template MERGES: every attribute of its groups that is not already
  // selected is appended; existing rows and entered values are never touched.
  const handleLoadTemplate = async (value: string) => {
    setTemplateValue(value);
    setTemplateLoading(true);
    try {
      const template = await fetchOneAttributeTemplate(value);
      const templateAttributes: Attribute[] = [];
      (template.groups ?? []).forEach((group) => {
        (group.attributes ?? []).forEach((attribute) => templateAttributes.push(attribute));
      });

      setExtraAttributes((prev) => {
        const known = new Set(prev.map((attribute) => attribute.id));
        return [...prev, ...templateAttributes.filter((attribute) => !known.has(attribute.id))];
      });

      const existing = new Set(fields.map((field) => field.attribute_id));
      templateAttributes
        .filter((attribute) => !existing.has(attribute.id))
        .forEach((attribute) => append(makeEmptyEntry(attribute)));
    } catch (error) {
      if (error instanceof ApiError) parseAndToastError(error);
      else toast.error(tMessage('error.general'));
    } finally {
      setTemplateLoading(false);
      setTemplateValue('');
    }
  };

  const isEntryUnset = (entry: ProductAttributeValueEntry) => {
    if (entry.data_type === 'select' || entry.data_type === 'multiselect') {
      return entry.value_ids.length === 0;
    }
    if (entry.data_type === 'text') return !entry.text_value?.trim();
    if (entry.data_type === 'number') return !entry.number_value?.trim();
    return false;
  };

  const renderValueControl = (index: number, entry: ProductAttributeValueEntry) => {
    if (entry.data_type === 'select' || entry.data_type === 'multiselect') {
      return (
        <AttributeValueField
          control={control}
          index={index}
          attributeId={entry.attribute_id}
          multiselect={entry.data_type === 'multiselect'}
        />
      );
    }
    if (entry.data_type === 'boolean') {
      return (
        <FormField
          control={control}
          name={`attribute_values.${index}.boolean_value`}
          render={({ field }) => (
            <FormItem className='m-0'>
              <FormControl>
                <Switch checked={field.value ?? false} onCheckedChange={field.onChange} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      );
    }
    return (
      <FormField
        control={control}
        name={`attribute_values.${index}.${entry.data_type === 'number' ? 'number_value' : 'text_value'}`}
        render={({ field }) => (
          <FormItem className='m-0'>
            <FormControl>
              <Input
                type={entry.data_type === 'number' ? 'number' : 'text'}
                step={entry.data_type === 'number' ? '0.01' : undefined}
                min={entry.data_type === 'number' ? 0 : undefined}
                placeholder={tPlaceHolder('input')}
                {...field}
                value={field.value ?? ''}
                onChange={(event) => field.onChange(event.target.value)}
              />
            </FormControl>
            <FormMessage />
          </FormItem>
        )}
      />
    );
  };

  return (
    <div className='space-y-4'>
      <p className='text-sm text-muted-foreground'>{tHelpText('attributes')}</p>

      <div className='flex flex-wrap gap-3'>
        <div className='w-full sm:w-64'>
          <Select
            value={templateValue || undefined}
            onValueChange={handleLoadTemplate}
            disabled={templateLoading || templates.length === 0}
          >
            <SelectTrigger className='w-full'>
              <SelectValue
                placeholder={
                  templateLoading
                    ? tMessage('info.loading')
                    : templates.length === 0
                      ? tLabel('no_templates')
                      : tLabel('load_from_template')
                }
              />
            </SelectTrigger>
            <SelectContent>
              {templates.map((template) => (
                <SelectItem key={template.id} value={String(template.id)}>
                  {template.title}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>

        <div className='w-full sm:w-64'>
          <Select
            value={addAttributeValue || undefined}
            onValueChange={handleAddAttribute}
            disabled={attributesLoading || availableCount === 0}
          >
            <SelectTrigger className='w-full'>
              <SelectValue
                placeholder={
                  attributesLoading
                    ? tMessage('info.loading')
                    : availableCount === 0
                      ? tLabel('no_more_attributes')
                      : tLabel('add_attribute')
                }
              />
            </SelectTrigger>
            <SelectContent>
              {orderedGroups.map((group) => {
                const groupAttributes = pickerGroups.known.get(group.id) ?? [];
                if (groupAttributes.length === 0) return null;
                return (
                  <SelectGroup key={group.id}>
                    <SelectLabel>{attributeGroupTitle(group)}</SelectLabel>
                    {groupAttributes.map((attribute) => (
                      <SelectItem key={attribute.id} value={String(attribute.id)}>
                        {attributeTitle(attribute)}
                      </SelectItem>
                    ))}
                  </SelectGroup>
                );
              })}
              {pickerGroups.ungrouped.length > 0 && (
                <SelectGroup>
                  <SelectLabel>{tLabel('ungrouped')}</SelectLabel>
                  {pickerGroups.ungrouped.map((attribute) => (
                    <SelectItem key={attribute.id} value={String(attribute.id)}>
                      {attributeTitle(attribute)}
                    </SelectItem>
                  ))}
                </SelectGroup>
              )}
            </SelectContent>
          </Select>
        </div>
      </div>

      {fields.length === 0 && !attributesLoading && (
        <p className='text-sm text-muted-foreground'>{tLabel('no_attributes_added')}</p>
      )}
      {attributesLoading && fields.length === 0 && (
        <div className='flex items-center gap-2 text-sm text-muted-foreground'>
          <LoaderIcon size={14} className='animate-spin' />
          {tMessage('info.loading')}
        </div>
      )}

      <div className='space-y-6'>
        {displaySections.map((section) => (
          <div key={section.key} className='space-y-2'>
            <h4 className='text-sm font-semibold text-muted-foreground'>{section.title}</h4>
            <div className='space-y-2'>
              {section.rows.map(({ index, attribute }) => {
                const entry: ProductAttributeValueEntry =
                  watchedEntries[index] ?? fields[index];
                return (
                  <div
                    key={fields[index].id}
                    className='flex flex-wrap items-center gap-3 rounded-md border p-3'
                  >
                    <div className='flex min-w-40 flex-1 flex-wrap items-center gap-2'>
                      <span className='text-sm font-medium'>
                        {attribute ? attributeTitle(attribute) : `#${entry.attribute_id}`}
                      </span>
                      <Badge
                        variant='outline'
                        className={dataTypeColors.get(entry.data_type)}
                      >
                        {tAttributeStatus(`data_type.${entry.data_type}`)}
                      </Badge>
                      {isEntryUnset(entry) && (
                        <span className='text-xs text-muted-foreground'>
                          ({tLabel('not_set')})
                        </span>
                      )}
                    </div>
                    <div className='w-full sm:w-72'>{renderValueControl(index, entry)}</div>
                    <Button
                      type='button'
                      variant='ghost'
                      size='sm'
                      className='text-destructive hover:text-destructive'
                      onClick={() => remove(index)}
                      aria-label={tLabel('remove_attribute')}
                    >
                      <XIcon size={16} />
                    </Button>
                  </div>
                );
              })}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
