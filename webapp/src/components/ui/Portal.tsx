import { createPortal } from 'react-dom';
import { ReactNode } from 'react';

interface PortalProps {
  children: ReactNode;
}

/**
 * Portal component that renders children directly into document.body.
 * This bypasses any CSS inheritance issues from parent elements,
 * ensuring position: fixed works correctly.
 */
export function Portal({ children }: PortalProps) {
  if (typeof document === 'undefined') {
    return null;
  }
  
  return createPortal(children, document.body);
}



