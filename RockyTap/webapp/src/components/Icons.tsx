/**
 * Ghidar Icon Library
 * Custom SVG icons for consistent visual language
 */

interface IconProps {
  size?: number;
  color?: string;
  className?: string;
}

export function HomeIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path
        d="M3 12L5 10M5 10L12 3L19 10M5 10V20C5 20.5523 5.44772 21 6 21H9M19 10L21 12M19 10V20C19 20.5523 18.5523 21 18 21H15M9 21C9.55228 21 10 20.5523 10 20V16C10 15.4477 10.4477 15 11 15H13C13.5523 15 14 15.4477 14 16V20C14 20.5523 14.4477 21 15 21M9 21H15"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export function LotteryIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <rect x="3" y="6" width="18" height="12" rx="2" stroke={color} strokeWidth="2" />
      <path d="M3 10H21" stroke={color} strokeWidth="2" />
      <circle cx="7" cy="14" r="1.5" fill={color} />
      <circle cx="12" cy="14" r="1.5" fill={color} />
      <circle cx="17" cy="14" r="1.5" fill={color} />
      <path d="M8 3L8 6" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M16 3L16 6" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function AirdropIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path
        d="M12 2L15 8L21 9L16.5 14L18 21L12 17.5L6 21L7.5 14L3 9L9 8L12 2Z"
        stroke={color}
        strokeWidth="2"
        strokeLinecap="round"
        strokeLinejoin="round"
      />
    </svg>
  );
}

export function TraderIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M3 17L9 11L13 15L21 7" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M16 7H21V12" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function ReferralIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="9" cy="7" r="3" stroke={color} strokeWidth="2" />
      <path d="M3 21V19C3 16.7909 4.79086 15 7 15H11C13.2091 15 15 16.7909 15 19V21" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <circle cx="17" cy="10" r="2.5" stroke={color} strokeWidth="2" />
      <path d="M17 14C19.2091 14 21 15.7909 21 18V20" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function ChevronRightIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M9 18L15 12L9 6" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function CoinIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="12" cy="12" r="9" stroke={color} strokeWidth="2" />
      <path d="M12 6V18M15 9C15 7.89543 13.6569 7 12 7C10.3431 7 9 7.89543 9 9C9 10.1046 10.3431 11 12 11C13.6569 11 15 11.8954 15 13C15 14.1046 13.6569 15 12 15C10.3431 15 9 14.1046 9 13" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function WalletIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <rect x="3" y="6" width="18" height="14" rx="2" stroke={color} strokeWidth="2" />
      <path d="M3 10H21" stroke={color} strokeWidth="2" />
      <circle cx="16" cy="14" r="1.5" fill={color} />
    </svg>
  );
}

export function TicketIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path 
        d="M4 6C4 4.89543 4.89543 4 6 4H18C19.1046 4 20 4.89543 20 6V8.5C18.6193 8.5 17.5 9.61929 17.5 11C17.5 12.3807 18.6193 13.5 20 13.5V16C20 17.1046 19.1046 18 18 18H6C4.89543 18 4 17.1046 4 16V13.5C5.38071 13.5 6.5 12.3807 6.5 11C6.5 9.61929 5.38071 8.5 4 8.5V6Z" 
        stroke={color} 
        strokeWidth="2" 
      />
      <path d="M10 8V14" stroke={color} strokeWidth="2" strokeLinecap="round" strokeDasharray="2 2" />
    </svg>
  );
}

export function CheckIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M5 12L10 17L20 7" stroke={color} strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function CloseIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M6 6L18 18M18 6L6 18" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function WarningIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M12 9V13M12 17H12.01" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M10.29 3.86L1.82 18C1.64 18.3 1.55 18.64 1.55 19C1.55 19.36 1.64 19.7 1.82 20C2 20.3 2.26 20.56 2.56 20.74C2.87 20.92 3.21 21.01 3.56 21.01H20.43C20.78 21.01 21.12 20.92 21.42 20.74C21.73 20.56 21.99 20.3 22.17 20C22.35 19.7 22.44 19.36 22.44 19C22.44 18.64 22.35 18.3 22.17 18L13.7 3.86C13.52 3.56 13.27 3.31 12.96 3.13C12.66 2.95 12.32 2.86 11.97 2.86C11.62 2.86 11.28 2.95 10.98 3.13C10.67 3.31 10.42 3.56 10.24 3.86H10.29Z" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function InfoIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="12" cy="12" r="9" stroke={color} strokeWidth="2" />
      <path d="M12 16V12" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <circle cx="12" cy="8" r="1" fill={color} />
    </svg>
  );
}

export function CopyIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <rect x="9" y="9" width="11" height="11" rx="2" stroke={color} strokeWidth="2" />
      <path d="M5 15H4C3.44772 15 3 14.5523 3 14V5C3 4.44772 3.44772 4 4 4H13C13.5523 4 14 4.44772 14 5V6" stroke={color} strokeWidth="2" />
    </svg>
  );
}

export function TrophyIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M6 9H4C3.44772 9 3 8.55228 3 8V5C3 4.44772 3.44772 4 4 4H6" stroke={color} strokeWidth="2" />
      <path d="M18 9H20C20.5523 9 21 8.55228 21 8V5C21 4.44772 20.5523 4 20 4H18" stroke={color} strokeWidth="2" />
      <path d="M6 4H18V12C18 15.3137 15.3137 18 12 18C8.68629 18 6 15.3137 6 12V4Z" stroke={color} strokeWidth="2" />
      <path d="M12 18V21" stroke={color} strokeWidth="2" />
      <path d="M8 21H16" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function HistoryIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="12" cy="12" r="9" stroke={color} strokeWidth="2" />
      <path d="M12 7V12L15 14" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function ArrowUpIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M12 19V5M5 12L12 5L19 12" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function ArrowDownIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M12 5V19M5 12L12 19L19 12" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function GiftIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <rect x="3" y="10" width="18" height="11" rx="2" stroke={color} strokeWidth="2" />
      <path d="M12 10V21" stroke={color} strokeWidth="2" />
      <path d="M3 14H21" stroke={color} strokeWidth="2" />
      <path d="M12 10C12 10 12 6 9 4C6 2 4 5 6 7C8 9 12 10 12 10Z" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M12 10C12 10 12 6 15 4C18 2 20 5 18 7C16 9 12 10 12 10Z" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function SparkleIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M12 2L14 8L20 8L15 12L17 18L12 14L7 18L9 12L4 8L10 8L12 2Z" fill={color} />
    </svg>
  );
}

export function RobotIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <rect x="4" y="8" width="16" height="12" rx="2" stroke={color} strokeWidth="2" />
      <circle cx="9" cy="13" r="1.5" fill={color} />
      <circle cx="15" cy="13" r="1.5" fill={color} />
      <path d="M10 17H14" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M12 4V8" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <circle cx="12" cy="3" r="1" fill={color} />
      <path d="M2 12V14" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M22 12V14" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function SettingsIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="12" cy="12" r="3" stroke={color} strokeWidth="2" />
      <path d="M12 1V3M12 21V23M4.22 4.22L5.64 5.64M18.36 18.36L19.78 19.78M1 12H3M21 12H23M4.22 19.78L5.64 18.36M18.36 5.64L19.78 4.22" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function BellIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M13.73 21a2 2 0 0 1-3.46 0" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function ChartIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M3 3v18h18" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M7 16l4-4 4 4 6-6" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function ActivityIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M22 12h-4l-3 9L9 3l-3 9H2" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function QRIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <rect x="3" y="3" width="5" height="5" stroke={color} strokeWidth="2" />
      <rect x="16" y="3" width="5" height="5" stroke={color} strokeWidth="2" />
      <rect x="3" y="16" width="5" height="5" stroke={color} strokeWidth="2" />
      <path d="M8 8h8M8 16h8M16 8v8" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function ClockIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="12" cy="12" r="10" stroke={color} strokeWidth="2" />
      <path d="M12 6v6l4 2" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function XIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M18 6L6 18M6 6l12 12" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function ShareIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M4 12V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V12" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M16 6L12 2L8 6" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M12 2V15" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function TelegramIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M22 2L11 13" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M22 2L15 22L11 13L2 9L22 2Z" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function QRCodeIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <rect x="3" y="3" width="7" height="7" rx="1" stroke={color} strokeWidth="2" />
      <rect x="14" y="3" width="7" height="7" rx="1" stroke={color} strokeWidth="2" />
      <rect x="3" y="14" width="7" height="7" rx="1" stroke={color} strokeWidth="2" />
      <rect x="14" y="14" width="3" height="3" fill={color} />
      <rect x="18" y="14" width="3" height="3" fill={color} />
      <rect x="14" y="18" width="3" height="3" fill={color} />
      <rect x="18" y="18" width="3" height="3" fill={color} />
      <rect x="5" y="5" width="3" height="3" fill={color} />
      <rect x="16" y="5" width="3" height="3" fill={color} />
      <rect x="5" y="16" width="3" height="3" fill={color} />
    </svg>
  );
}

export function UserGroupIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="9" cy="7" r="3" stroke={color} strokeWidth="2" />
      <path d="M2 21V19C2 16.7909 3.79086 15 6 15H12C14.2091 15 16 16.7909 16 19V21" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <circle cx="17" cy="8" r="2.5" stroke={color} strokeWidth="2" />
      <path d="M17 13C19.2091 13 21 14.7909 21 17V19" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function UserPlusIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="10" cy="8" r="4" stroke={color} strokeWidth="2" />
      <path d="M2 21V19C2 16.2386 4.23858 14 7 14H13C15.7614 14 18 16.2386 18 19V21" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M20 8V14" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M23 11H17" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function DollarIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="12" cy="12" r="9" stroke={color} strokeWidth="2" />
      <path d="M12 6V18" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M15 9.5C15 8.11929 13.6569 7 12 7C10.3431 7 9 8.11929 9 9.5C9 10.8807 10.3431 12 12 12C13.6569 12 15 13.1193 15 14.5C15 15.8807 13.6569 17 12 17C10.3431 17 9 15.8807 9 14.5" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function CrownIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M2 17L4 7L8 10L12 4L16 10L20 7L22 17H2Z" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M2 17H22V20C22 20.5523 21.5523 21 21 21H3C2.44772 21 2 20.5523 2 20V17Z" stroke={color} strokeWidth="2" />
    </svg>
  );
}

export function LinkIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M10 13C10.4295 13.5741 10.9774 14.0491 11.6066 14.3929C12.2357 14.7367 12.9315 14.9411 13.6467 14.9923C14.3618 15.0435 15.0796 14.9404 15.7513 14.6897C16.4231 14.439 17.0331 14.0471 17.54 13.54L20.54 10.54C21.4508 9.59695 21.9548 8.33394 21.9434 7.02296C21.932 5.71198 21.4061 4.45791 20.479 3.53087C19.5519 2.60383 18.2979 2.07799 16.9869 2.0666C15.6759 2.0552 14.4129 2.55918 13.47 3.46997L11.75 5.17997" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M14 11C13.5705 10.4259 13.0226 9.95087 12.3934 9.60705C11.7642 9.26323 11.0685 9.05889 10.3533 9.00768C9.63816 8.95648 8.92037 9.05963 8.24861 9.31029C7.57685 9.56095 6.96684 9.9529 6.45996 10.46L3.45996 13.46C2.54917 14.403 2.04519 15.666 2.05659 16.977C2.06798 18.288 2.59382 19.5421 3.52086 20.4691C4.4479 21.3962 5.70197 21.922 7.01295 21.9334C8.32393 21.9448 9.58694 21.4408 10.53 20.53L12.24 18.82" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function FireIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M12 22C16.4183 22 20 18.4183 20 14C20 9.58172 12 2 12 2C12 2 4 9.58172 4 14C4 18.4183 7.58172 22 12 22Z" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M12 22C14.2091 22 16 19.9853 16 17.5C16 15.0147 12 10 12 10C12 10 8 15.0147 8 17.5C8 19.9853 9.79086 22 12 22Z" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

// Additional icons for verification and security flows
export function ShieldIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function ShieldCheckIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M12 22C12 22 20 18 20 12V5L12 2L4 5V12C4 18 12 22 12 22Z" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M9 12L11 14L15 10" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function KeyIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M21 2L19 4M19 4L22 7L18.5 10.5L15.5 7.5M19 4L15.5 7.5M11.4 11.4C12.2 12.1 12.8 13 13.1 14C13.4 15 13.4 16.1 13.1 17.1C12.8 18.1 12.2 19 11.4 19.8C10.6 20.6 9.7 21.2 8.6 21.5C7.6 21.8 6.5 21.8 5.5 21.5C4.5 21.2 3.6 20.6 2.8 19.8C2 19 1.4 18.1 1.1 17.1C0.800003 16.1 0.800003 15 1.1 14C1.4 13 2 12.1 2.8 11.4C3.6 10.6 4.5 10 5.5 9.7C6.5 9.4 7.6 9.4 8.6 9.7C9.7 10 10.6 10.6 11.4 11.4ZM11.4 11.4L15.5 7.5" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function LockIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <rect x="3" y="11" width="18" height="11" rx="2" stroke={color} strokeWidth="2" />
      <path d="M7 11V7C7 4.23858 9.23858 2 12 2C14.7614 2 17 4.23858 17 7V11" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function AlertTriangleIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M10.29 3.86L1.82 18C1.64 18.3 1.55 18.64 1.55 19C1.55 19.36 1.64 19.7 1.82 20C2 20.3 2.26 20.56 2.56 20.74C2.87 20.92 3.21 21.01 3.56 21.01H20.43C20.78 21.01 21.12 20.92 21.42 20.74C21.73 20.56 21.99 20.3 22.17 20C22.35 19.7 22.44 19.36 22.44 19C22.44 18.64 22.35 18.3 22.17 18L13.7 3.86C13.52 3.56 13.27 3.31 12.96 3.13C12.66 2.95 12.32 2.86 11.97 2.86C11.62 2.86 11.28 2.95 10.98 3.13C10.67 3.31 10.42 3.56 10.24 3.86H10.29Z" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M12 9V13" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <circle cx="12" cy="17" r="1" fill={color} />
    </svg>
  );
}

export function CheckCircleIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="12" cy="12" r="10" stroke={color} strokeWidth="2" />
      <path d="M9 12L11 14L15 10" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function HelpCircleIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="12" cy="12" r="10" stroke={color} strokeWidth="2" />
      <path d="M9.09 9C9.3 8.33 9.79 7.79 10.42 7.47C11.05 7.14 11.77 7.05 12.46 7.21C13.14 7.37 13.74 7.77 14.15 8.32C14.56 8.88 14.74 9.56 14.67 10.25C14.67 12 11.92 12.75 11.92 12.75" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx="12" cy="17" r="1" fill={color} />
    </svg>
  );
}

export function LoaderIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M12 2V6" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M12 18V22" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M4.93 4.93L7.76 7.76" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M16.24 16.24L19.07 19.07" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M2 12H6" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M18 12H22" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M4.93 19.07L7.76 16.24" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M16.24 7.76L19.07 4.93" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function SmartphoneIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <rect x="5" y="2" width="14" height="20" rx="2" stroke={color} strokeWidth="2" />
      <path d="M12 18H12.01" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function MonitorIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <rect x="2" y="3" width="20" height="14" rx="2" stroke={color} strokeWidth="2" />
      <path d="M8 21H16" stroke={color} strokeWidth="2" strokeLinecap="round" />
      <path d="M12 17V21" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function ExternalLinkIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M18 13V19C18 19.5304 17.7893 20.0391 17.4142 20.4142C17.0391 20.7893 16.5304 21 16 21H5C4.46957 21 3.96086 20.7893 3.58579 20.4142C3.21071 20.0391 3 19.5304 3 19V8C3 7.46957 3.21071 6.96086 3.58579 6.58579C3.96086 6.21071 4.46957 6 5 6H11" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M15 3H21V9" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M10 14L21 3" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function ChevronDownIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M6 9L12 15L18 9" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function ChevronUpIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M18 15L12 9L6 15" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function MessageCircleIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M21 11.5C21.0034 12.8199 20.6951 14.1219 20.1 15.3C19.3944 16.7118 18.3098 17.8992 16.9674 18.7293C15.6251 19.5594 14.0782 19.9994 12.5 20C11.1801 20.0035 9.87812 19.6951 8.7 19.1L3 21L4.9 15.3C4.30493 14.1219 3.99656 12.8199 4 11.5C4.00061 9.92179 4.44061 8.37488 5.27072 7.03258C6.10083 5.69028 7.28825 4.6056 8.7 3.90003C9.87812 3.30496 11.1801 2.99659 12.5 3.00003H13C15.0843 3.11502 17.053 3.99479 18.5291 5.47089C20.0052 6.94699 20.885 8.91568 21 11V11.5Z" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function UserIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="12" cy="8" r="4" stroke={color} strokeWidth="2" />
      <path d="M4 21V19C4 16.7909 5.79086 15 8 15H16C18.2091 15 20 16.7909 20 19V21" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

export function EyeIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M1 12C1 12 5 4 12 4C19 4 23 12 23 12C23 12 19 20 12 20C5 20 1 12 1 12Z" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <circle cx="12" cy="12" r="3" stroke={color} strokeWidth="2" />
    </svg>
  );
}

export function CreditCardIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <rect x="1" y="4" width="22" height="16" rx="2" stroke={color} strokeWidth="2" />
      <path d="M1 10H23" stroke={color} strokeWidth="2" />
    </svg>
  );
}

export function XCircleIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <circle cx="12" cy="12" r="10" stroke={color} strokeWidth="2" />
      <path d="M15 9L9 15M9 9L15 15" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function ArrowRightLeftIcon({ size = 24, color = 'currentColor', className = '' }: IconProps) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" className={className}>
      <path d="M17 4L21 8L17 12" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M21 8H9" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M7 20L3 16L7 12" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M3 16H15" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

