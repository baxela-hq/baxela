import { type z } from 'zod';
import { type Control, useWatch } from 'react-hook-form';
import { useAppTranslation } from '@/hooks/useAppTranslation';
import {
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';
import { Input } from '@/components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Locales } from '../data/routes';
import { DIMENSION_UNITS, WEIGHT_UNITS, type formSchema } from '../data/schema';

type ShippingControl = Control<
  z.input<typeof formSchema>,
  undefined,
  z.output<typeof formSchema>
>;

type ProductShippingTabProps = {
  control: ShippingControl;
};

function MeasurementInput({
  control,
  name,
  label,
  placeholder,
  disabled,
}: {
  control: ShippingControl;
  name: `shipping.${'weight' | 'package_length' | 'package_width' | 'package_height'}`;
  label: string;
  placeholder: string;
  disabled: boolean;
}) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <Input
              type='number'
              step='0.01'
              min={0}
              placeholder={placeholder}
              disabled={disabled}
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
}

export function ProductShippingTab({ control }: ProductShippingTabProps) {
  const { tLabel, tStatus, tTooltip, tHelpText } = useAppTranslation(Locales.PRODUCT);
  const { tPlaceHolder } = useAppTranslation(Locales.SHARED_COMMON);

  // Measurements are meaningless for products that don't require shipping —
  // the API still accepts them, so they are kept in state but greyed out.
  const requiresShipping = useWatch({ control, name: 'shipping.requires_shipping' }) ?? true;

  return (
    <div className='space-y-6'>
      <p className='text-sm text-muted-foreground'>{tHelpText('shipping')}</p>

      <FormField
        control={control}
        name='shipping.requires_shipping'
        render={({ field }) => (
          <FormItem className='flex flex-row items-center justify-between rounded-lg border p-4'>
            <div className='space-y-0.5'>
              <FormLabel className='text-base'>{tLabel('requires_shipping')}</FormLabel>
              <FormDescription>
                {tTooltip(`requires_shipping.${field.value ? 'active' : 'inactive'}`)}
              </FormDescription>
            </div>
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} />
            </FormControl>
          </FormItem>
        )}
      />

      <div className='grid gap-4 sm:grid-cols-2'>
        <MeasurementInput
          control={control}
          name='shipping.weight'
          label={tLabel('weight')}
          placeholder='1.20'
          disabled={!requiresShipping}
        />
        <FormField
          control={control}
          name='shipping.weight_unit'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{tLabel('weight_unit')}</FormLabel>
              <Select
                value={field.value ?? undefined}
                onValueChange={field.onChange}
                disabled={!requiresShipping}
              >
                <FormControl>
                  <SelectTrigger className='w-full'>
                    <SelectValue placeholder={tPlaceHolder('select')} />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {WEIGHT_UNITS.map((unit) => (
                    <SelectItem key={unit} value={unit}>
                      {tStatus(`weight_unit.${unit}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>

      <div className='grid gap-4 sm:grid-cols-2 lg:grid-cols-4'>
        <MeasurementInput
          control={control}
          name='shipping.package_length'
          label={tLabel('package_length')}
          placeholder='30.00'
          disabled={!requiresShipping}
        />
        <MeasurementInput
          control={control}
          name='shipping.package_width'
          label={tLabel('package_width')}
          placeholder='20.00'
          disabled={!requiresShipping}
        />
        <MeasurementInput
          control={control}
          name='shipping.package_height'
          label={tLabel('package_height')}
          placeholder='5.00'
          disabled={!requiresShipping}
        />
        <FormField
          control={control}
          name='shipping.dimension_unit'
          render={({ field }) => (
            <FormItem>
              <FormLabel>{tLabel('dimension_unit')}</FormLabel>
              <Select
                value={field.value ?? undefined}
                onValueChange={field.onChange}
                disabled={!requiresShipping}
              >
                <FormControl>
                  <SelectTrigger className='w-full'>
                    <SelectValue placeholder={tPlaceHolder('select')} />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {DIMENSION_UNITS.map((unit) => (
                    <SelectItem key={unit} value={unit}>
                      {tStatus(`dimension_unit.${unit}`)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />
      </div>
    </div>
  );
}
