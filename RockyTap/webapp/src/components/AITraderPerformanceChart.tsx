import { useState, useEffect, useMemo } from 'react';
import styles from './AITraderPerformanceChart.module.css';

interface ChartDataPoint {
  date: string;
  value: number;
  profit: number;
}

interface AITraderPerformanceChartProps {
  initialBalance?: number;
  dailyReturnMin?: number;
  dailyReturnMax?: number;
}

function generateChartData(
  days: number,
  initialBalance: number,
  dailyReturnMin: number,
  dailyReturnMax: number
): ChartDataPoint[] {
  const data: ChartDataPoint[] = [];
  let balance = initialBalance;
  
  for (let i = days - 1; i >= 0; i--) {
    const date = new Date();
    date.setDate(date.getDate() - i);
    
    const dailyReturn = dailyReturnMin + Math.random() * (dailyReturnMax - dailyReturnMin);
    const profit = balance * (dailyReturn / 100);
    
    if (i < days - 1) {
      balance += profit;
    }
    
    data.push({
      date: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
      value: balance,
      profit: profit,
    });
  }
  
  return data;
}

export function AITraderPerformanceChart({
  initialBalance = 1000,
  dailyReturnMin = 2.0,
  dailyReturnMax = 3.0,
}: AITraderPerformanceChartProps) {
  const [period, setPeriod] = useState<'7d' | '30d'>('7d');
  const [animationProgress, setAnimationProgress] = useState(0);
  
  const chartData = useMemo(() => {
    const days = period === '7d' ? 7 : 30;
    return generateChartData(days, initialBalance, dailyReturnMin, dailyReturnMax);
  }, [period, initialBalance, dailyReturnMin, dailyReturnMax]);
  
  // Animate chart on load/period change
  useEffect(() => {
    setAnimationProgress(0);
    const duration = 1000;
    const startTime = Date.now();
    
    const animate = () => {
      const elapsed = Date.now() - startTime;
      const progress = Math.min(elapsed / duration, 1);
      setAnimationProgress(progress);
      
      if (progress < 1) {
        requestAnimationFrame(animate);
      }
    };
    
    requestAnimationFrame(animate);
  }, [period]);
  
  // Calculate chart dimensions
  const chartHeight = 120;
  const chartWidth = 100; // percentage
  
  const values = chartData.map(d => d.value);
  const minValue = Math.min(...values) * 0.98;
  const maxValue = Math.max(...values) * 1.02;
  const range = maxValue - minValue;
  
  // Generate SVG path
  const getPath = () => {
    const points = chartData.map((d, i) => {
      const x = (i / (chartData.length - 1)) * 100;
      const y = chartHeight - ((d.value - minValue) / range) * chartHeight;
      return `${x},${y}`;
    });
    
    return `M ${points.join(' L ')}`;
  };
  
  // Generate area path
  const getAreaPath = () => {
    const points = chartData.map((d, i) => {
      const x = (i / (chartData.length - 1)) * 100;
      const y = chartHeight - ((d.value - minValue) / range) * chartHeight;
      return `${x},${y}`;
    });
    
    return `M 0,${chartHeight} L ${points.join(' L ')} L 100,${chartHeight} Z`;
  };
  
  // Calculate totals
  const totalProfit = chartData.reduce((sum, d) => sum + d.profit, 0);
  const totalReturnPercent = (totalProfit / initialBalance) * 100;
  const currentValue = chartData[chartData.length - 1]?.value || initialBalance;

  return (
    <div className={styles.container}>
      <div className={styles.header}>
        <div className={styles.titleSection}>
          <h3 className={styles.title}>Performance</h3>
          <div className={styles.returnBadge}>
            <span className={styles.returnIcon}>📈</span>
            <span className={styles.returnValue}>+{totalReturnPercent.toFixed(2)}%</span>
          </div>
        </div>
        
        <div className={styles.periodToggle}>
          <button
            className={`${styles.periodButton} ${period === '7d' ? styles.active : ''}`}
            onClick={() => setPeriod('7d')}
          >
            7D
          </button>
          <button
            className={`${styles.periodButton} ${period === '30d' ? styles.active : ''}`}
            onClick={() => setPeriod('30d')}
          >
            30D
          </button>
        </div>
      </div>
      
      <div className={styles.valueDisplay}>
        <span className={styles.currentLabel}>Current Value</span>
        <span className={styles.currentValue}>${currentValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
        <span className={styles.profitValue}>
          +${totalProfit.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}
        </span>
      </div>
      
      <div className={styles.chartContainer}>
        <svg
          viewBox={`0 0 100 ${chartHeight}`}
          preserveAspectRatio="none"
          className={styles.chart}
        >
          <defs>
            <linearGradient id="chartGradient" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" stopColor="rgba(16, 185, 129, 0.3)" />
              <stop offset="100%" stopColor="rgba(16, 185, 129, 0)" />
            </linearGradient>
            <linearGradient id="lineGradient" x1="0%" y1="0%" x2="100%" y2="0%">
              <stop offset="0%" stopColor="#10b981" />
              <stop offset="100%" stopColor="#34d399" />
            </linearGradient>
          </defs>
          
          {/* Grid lines */}
          {[0, 25, 50, 75, 100].map((y) => (
            <line
              key={y}
              x1="0"
              y1={(y / 100) * chartHeight}
              x2="100"
              y2={(y / 100) * chartHeight}
              stroke="rgba(255, 255, 255, 0.05)"
              strokeWidth="0.3"
            />
          ))}
          
          {/* Area */}
          <path
            d={getAreaPath()}
            fill="url(#chartGradient)"
            style={{
              opacity: animationProgress,
              transform: `scaleY(${animationProgress})`,
              transformOrigin: 'bottom',
            }}
          />
          
          {/* Line */}
          <path
            d={getPath()}
            fill="none"
            stroke="url(#lineGradient)"
            strokeWidth="2"
            strokeLinecap="round"
            strokeLinejoin="round"
            style={{
              strokeDasharray: 1000,
              strokeDashoffset: 1000 - (1000 * animationProgress),
            }}
          />
          
          {/* Current point */}
          <circle
            cx="100"
            cy={chartHeight - ((currentValue - minValue) / range) * chartHeight}
            r="3"
            fill="#10b981"
            style={{ opacity: animationProgress }}
          />
          <circle
            cx="100"
            cy={chartHeight - ((currentValue - minValue) / range) * chartHeight}
            r="6"
            fill="rgba(16, 185, 129, 0.3)"
            style={{ opacity: animationProgress }}
          />
        </svg>
        
        {/* X-axis labels */}
        <div className={styles.xAxis}>
          {chartData.filter((_, i) => i % (period === '7d' ? 1 : 5) === 0).map((d, i) => (
            <span key={i} className={styles.xLabel}>{d.date}</span>
          ))}
        </div>
      </div>
      
      {/* Daily breakdown */}
      <div className={styles.dailyStats}>
        <div className={styles.dailyStat}>
          <span className={styles.dailyLabel}>Avg Daily Return</span>
          <span className={styles.dailyValue}>
            +{((dailyReturnMin + dailyReturnMax) / 2).toFixed(2)}%
          </span>
        </div>
        <div className={styles.dailyDivider} />
        <div className={styles.dailyStat}>
          <span className={styles.dailyLabel}>Profitable Days</span>
          <span className={styles.dailyValue}>{chartData.length}/{chartData.length}</span>
        </div>
        <div className={styles.dailyDivider} />
        <div className={styles.dailyStat}>
          <span className={styles.dailyLabel}>Best Day</span>
          <span className={styles.dailyValue}>
            +${Math.max(...chartData.map(d => d.profit)).toFixed(2)}
          </span>
        </div>
      </div>
    </div>
  );
}

