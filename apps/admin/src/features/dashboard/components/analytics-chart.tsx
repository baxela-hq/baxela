import { Area, AreaChart, ResponsiveContainer, XAxis, YAxis } from 'recharts'
import { formatDayLabel } from '../data/format'

export function AnalyticsChart({
  data,
}: {
  data: { date: string; count: number }[]
}) {
  return (
    <ResponsiveContainer width='100%' height={300}>
      <AreaChart data={data}>
        <XAxis
          dataKey='date'
          stroke='#888888'
          fontSize={12}
          tickLine={false}
          axisLine={false}
          minTickGap={24}
          tickFormatter={(day) => formatDayLabel(day)}
        />
        <YAxis
          direction='ltr'
          stroke='#888888'
          fontSize={12}
          tickLine={false}
          axisLine={false}
          allowDecimals={false}
        />
        <Area
          type='monotone'
          dataKey='count'
          stroke='currentColor'
          className='text-primary'
          fill='currentColor'
          fillOpacity={0.15}
        />
      </AreaChart>
    </ResponsiveContainer>
  )
}
