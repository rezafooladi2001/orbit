import { useState, useRef, useEffect } from 'react';
import styles from './Tooltip.module.css';

interface TooltipProps {
  content: string;
  children: React.ReactNode;
  position?: 'top' | 'bottom' | 'left' | 'right';
  className?: string;
}

export function Tooltip({ content, children, position = 'top', className = '' }: TooltipProps) {
  const [isVisible, setIsVisible] = useState(false);
  const tooltipRef = useRef<HTMLDivElement>(null);
  const triggerRef = useRef<HTMLDivElement>(null);

  const showTooltip = () => setIsVisible(true);
  const hideTooltip = () => setIsVisible(false);

  // Handle click outside to close
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (tooltipRef.current && !tooltipRef.current.contains(event.target as Node)) {
        setIsVisible(false);
      }
    };

    if (isVisible) {
      document.addEventListener('mousedown', handleClickOutside);
    }

    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isVisible]);

  return (
    <div 
      className={`${styles.container} ${className}`}
      ref={triggerRef}
    >
      <div 
        className={styles.trigger}
        onClick={() => setIsVisible(!isVisible)}
        onMouseEnter={showTooltip}
        onMouseLeave={hideTooltip}
      >
        {children}
      </div>
      {isVisible && (
        <div 
          ref={tooltipRef}
          className={`${styles.tooltip} ${styles[position]}`}
          role="tooltip"
        >
          <div className={styles.content}>{content}</div>
          <div className={styles.arrow} />
        </div>
      )}
    </div>
  );
}

interface HelpTooltipProps {
  content: string;
  className?: string;
}

export function HelpTooltip({ content, className = '' }: HelpTooltipProps) {
  return (
    <Tooltip content={content} className={className}>
      <button className={styles.helpButton} aria-label="Help">
        <span className={styles.helpIcon}>?</span>
      </button>
    </Tooltip>
  );
}

