import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts';

interface DashboardChartProps {
  data: any[];
  xKey: string;
  yKey: string;
  title: string;
  color?: string;
}

export const DashboardChart = ({ data, xKey, yKey, title, color = "#8884d8" }: DashboardChartProps) => {
  return (
    <div className="bg-white p-6 rounded shadow mt-6">
      <h3 className="text-lg font-semibold mb-4">{title}</h3>
      <ResponsiveContainer width="100%" height={300}>
        <BarChart data={data}>
          <CartesianGrid strokeDasharray="3 3" />
          <XAxis dataKey={xKey} />
          <YAxis />
          <Tooltip />
          <Bar dataKey={yKey} fill={color} />
        </BarChart>
      </ResponsiveContainer>
    </div>
  );
};
