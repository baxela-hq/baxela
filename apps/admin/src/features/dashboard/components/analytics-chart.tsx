import { Area, AreaChart, ResponsiveContainer, XAxis, YAxis } from 'recharts'
import { useAppTranslation } from '@/hooks/useAppTranslation'
import { Locales } from '../data/routes'

const data = [
  {
    day: 'mon',
    clicks: Math.floor(Math.random() * 900) + 100,
    uniques: Math.floor(Math.random() * 700) + 80,
  },
  {
    day: 'tue',
    clicks: Math.floor(Math.random() * 900) + 100,
    uniques: Math.floor(Math.random() * 700) + 80,
  },
  {
    day: 'wed',
    clicks: Math.floor(Math.random() * 900) + 100,
    uniques: Math.floor(Math.random() * 700) + 80,
  },
  {
    day: 'thu',
    clicks: Math.floor(Math.random() * 900) + 100,
    uniques: Math.floor(Math.random() * 700) + 80,
  },
  {
    day: 'fri',
    clicks: Math.floor(Math.random() * 900) + 100,
    uniques: Math.floor(Math.random() * 700) + 80,
  },
  {
    day: 'sat',
    clicks: Math.floor(Math.random() * 900) + 100,
    uniques: Math.floor(Math.random() * 700) + 80,
  },
  {
    day: 'sun',
    clicks: Math.floor(Math.random() * 900) + 100,
    uniques: Math.floor(Math.random() * 700) + 80,
  },
]

export function AnalyticsChart() {
  const { t } = useAppTranslation(Locales.DASHBOARD)

  return (
    <ResponsiveContainer width='100%' height={300}>
      <AreaChart data={data}>
        <XAxis
          dataKey='day'
          stroke='#888888'
          fontSize={12}
          tickLine={false}
          axisLine={false}
          tickFormatter={(day) => t(`analytics.chart-days.${day}`)}
        />
        <YAxis
          stroke='#888888'
          fontSize={12}
          tickLine={false}
          axisLine={false}
        />
        <Area
          type='monotone'
          dataKey='clicks'
          stroke='currentColor'
          className='text-primary'
          fill='currentColor'
          fillOpacity={0.15}
        />
        <Area
          type='monotone'
          dataKey='uniques'
          stroke='currentColor'
          className='text-muted-foreground'
          fill='currentColor'
          fillOpacity={0.1}
        />
      </AreaChart>
    </ResponsiveContainer>
  )
}
