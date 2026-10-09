import { Bar, BarChart, ResponsiveContainer, XAxis, YAxis } from 'recharts'
import { formatCompactNumber, formatMonthLabel } from '../data/format'

export function Overview({
  data,
}: {
  data: { month: string; total: string }[]
}) {
  const chartData = data.map((point) => ({
    name: formatMonthLabel(point.month),
    total: Number(point.total),
  }))

  return (
    <ResponsiveContainer width='100%' height={350}>
      <BarChart data={chartData}>
        <XAxis
          dataKey='name'
          stroke='#888888'
          fontSize={12}
          tickLine={false}
          axisLine={false}
        />
        <YAxis
          direction='ltr'
          stroke='#888888'
          fontSize={12}
          tickLine={false}
          axisLine={false}
          width={64}
          tickFormatter={(value) => formatCompactNumber(Number(value))}
        />
        <Bar
          dataKey='total'
          fill='currentColor'
          radius={[4, 4, 0, 0]}
          className='fill-primary'
        />
      </BarChart>
    </ResponsiveContainer>
  )
}
