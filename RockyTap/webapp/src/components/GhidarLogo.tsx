/**
 * Ghidar Logo Component
 * A distinctive, modern logo with the brand identity
 */

interface GhidarLogoProps {
  size?: 'sm' | 'md' | 'lg' | 'xl';
  showText?: boolean;
  animate?: boolean;
  className?: string;
}

const sizes = {
  sm: { icon: 24, text: 16, gap: 6 },
  md: { icon: 32, text: 20, gap: 8 },
  lg: { icon: 48, text: 28, gap: 10 },
  xl: { icon: 64, text: 36, gap: 12 },
};

export function GhidarLogo({ 
  size = 'md', 
  showText = true, 
  animate = false,
  className = '' 
}: GhidarLogoProps) {
  const { icon, text, gap } = sizes[size];
  
  return (
    <div 
      className={className}
      style={{ 
        display: 'flex', 
        alignItems: 'center', 
        gap: `${gap}px`,
      }}
    >
      {/* Logo Icon - Abstract G with gem/diamond motif */}
      <svg 
        width={icon} 
        height={icon} 
        viewBox="0 0 48 48" 
        fill="none"
        style={{
          filter: animate ? 'drop-shadow(0 0 8px rgba(16, 185, 129, 0.4))' : undefined,
          animation: animate ? 'float 3s ease-in-out infinite' : undefined,
        }}
      >
        <defs>
          <linearGradient id="ghidar-gradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stopColor="#10b981" />
            <stop offset="50%" stopColor="#34d399" />
            <stop offset="100%" stopColor="#fbbf24" />
          </linearGradient>
          <linearGradient id="ghidar-gold" x1="0%" y1="100%" x2="100%" y2="0%">
            <stop offset="0%" stopColor="#f59e0b" />
            <stop offset="100%" stopColor="#fcd34d" />
          </linearGradient>
          <filter id="glow">
            <feGaussianBlur stdDeviation="2" result="coloredBlur"/>
            <feMerge>
              <feMergeNode in="coloredBlur"/>
              <feMergeNode in="SourceGraphic"/>
            </feMerge>
          </filter>
        </defs>
        
        {/* Main diamond/gem shape */}
        <path 
          d="M24 4L44 18L36 44H12L4 18L24 4Z" 
          fill="url(#ghidar-gradient)"
          opacity="0.15"
        />
        
        {/* Inner faceted gem structure */}
        <path 
          d="M24 8L40 20L34 40H14L8 20L24 8Z" 
          stroke="url(#ghidar-gradient)"
          strokeWidth="2"
          fill="none"
        />
        
        {/* Center facet lines */}
        <path 
          d="M24 8L24 40M8 20H40M14 40L24 24L34 40M24 24L8 20M24 24L40 20" 
          stroke="url(#ghidar-gradient)"
          strokeWidth="1.5"
          opacity="0.6"
          fill="none"
        />
        
        {/* Gold accent highlight */}
        <circle 
          cx="24" 
          cy="24" 
          r="6" 
          fill="url(#ghidar-gold)"
          filter={animate ? "url(#glow)" : undefined}
        />
        
        {/* Inner gem core */}
        <circle 
          cx="24" 
          cy="24" 
          r="3" 
          fill="#0a0c10"
        />
      </svg>
      
      {showText && (
        <span
          style={{
            fontFamily: "'Sora', sans-serif",
            fontSize: `${text}px`,
            fontWeight: 700,
            letterSpacing: '-0.5px',
            background: 'linear-gradient(135deg, #10b981 0%, #34d399 50%, #fbbf24 100%)',
            WebkitBackgroundClip: 'text',
            WebkitTextFillColor: 'transparent',
            backgroundClip: 'text',
          }}
        >
          Ghidar
        </span>
      )}
    </div>
  );
}

/**
 * Animated coin icon for tapping
 */
export function GhidarCoin({ 
  size = 120,
  animate = false,
  className = '' 
}: { 
  size?: number;
  animate?: boolean;
  className?: string;
}) {
  return (
    <svg 
      width={size} 
      height={size} 
      viewBox="0 0 120 120" 
      fill="none"
      className={className}
      style={{
        filter: 'drop-shadow(0 4px 20px rgba(16, 185, 129, 0.3))',
      }}
    >
      <defs>
        <linearGradient id="coin-outer" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#10b981" />
          <stop offset="100%" stopColor="#059669" />
        </linearGradient>
        <linearGradient id="coin-inner" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="#fbbf24" />
          <stop offset="100%" stopColor="#f59e0b" />
        </linearGradient>
        <linearGradient id="coin-shine" x1="0%" y1="0%" x2="0%" y2="100%">
          <stop offset="0%" stopColor="rgba(255,255,255,0.4)" />
          <stop offset="50%" stopColor="rgba(255,255,255,0)" />
        </linearGradient>
        <radialGradient id="coin-glow" cx="50%" cy="50%" r="50%">
          <stop offset="60%" stopColor="transparent" />
          <stop offset="100%" stopColor="rgba(16, 185, 129, 0.3)" />
        </radialGradient>
      </defs>
      
      {/* Outer glow ring */}
      <circle 
        cx="60" 
        cy="60" 
        r="58" 
        fill="url(#coin-glow)"
        style={animate ? { animation: 'pulse 2s ease-in-out infinite' } : undefined}
      />
      
      {/* Outer ring */}
      <circle 
        cx="60" 
        cy="60" 
        r="55" 
        fill="url(#coin-outer)"
      />
      
      {/* Inner coin face */}
      <circle 
        cx="60" 
        cy="60" 
        r="45" 
        fill="url(#coin-inner)"
      />
      
      {/* Inner decorative ring */}
      <circle 
        cx="60" 
        cy="60" 
        r="38" 
        fill="none"
        stroke="rgba(0,0,0,0.2)"
        strokeWidth="2"
      />
      
      {/* G Letter / Gem symbol in center */}
      <path 
        d="M60 35L75 50L68 75H52L45 50L60 35Z" 
        fill="#0a0c10"
        opacity="0.8"
      />
      <path 
        d="M60 40L70 52L65 70H55L50 52L60 40Z" 
        fill="url(#coin-outer)"
      />
      <circle 
        cx="60" 
        cy="55" 
        r="8" 
        fill="#fcd34d"
      />
      
      {/* Shine effect */}
      <ellipse 
        cx="45" 
        cy="45" 
        rx="15" 
        ry="10" 
        fill="url(#coin-shine)"
        transform="rotate(-30 45 45)"
      />
    </svg>
  );
}

