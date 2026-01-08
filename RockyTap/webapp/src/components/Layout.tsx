import React from 'react';
import { NavTabs, TabId, OfflineBanner } from './ui';
import { GhidarLogo } from './GhidarLogo';
import { Footer } from './Footer';
import styles from './Layout.module.css';

interface LayoutProps {
  children: React.ReactNode;
  activeTab: TabId;
  onTabChange: (tab: TabId) => void;
}

export function Layout({ children, activeTab, onTabChange }: LayoutProps) {
  return (
    <div className={styles.layout}>
      <a href="#main-content" className="skip-link">
        Skip to main content
      </a>
      <OfflineBanner />
      <header className={styles.header} role="banner">
        <div className={styles.headerContent}>
          <GhidarLogo size="md" animate aria-label="Ghidar Logo" />
        </div>
        <div className={styles.headerGlow} aria-hidden="true" />
      </header>
      
      <main id="main-content" className={styles.main} role="main">
        {children}
        {/* Footer is inside main - scrolls with content, visible at bottom of page */}
        <Footer />
      </main>
      
      {/* NavTabs is fixed at bottom of viewport - always visible */}
      <NavTabs activeTab={activeTab} onTabChange={onTabChange} />
    </div>
  );
}
