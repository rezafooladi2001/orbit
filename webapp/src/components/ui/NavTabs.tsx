import { HomeIcon, LotteryIcon, AirdropIcon, TraderIcon, ReferralIcon, SettingsIcon } from '../Icons';
import styles from './NavTabs.module.css';

export type TabId = 'home' | 'lottery' | 'airdrop' | 'trader' | 'referral' | 'settings';

interface NavTabsProps {
  activeTab: TabId;
  onTabChange: (tab: TabId) => void;
}

interface TabConfig {
  id: TabId;
  label: string;
  Icon: typeof HomeIcon;
}

const tabs: TabConfig[] = [
  { id: 'home', label: 'Home', Icon: HomeIcon },
  { id: 'lottery', label: 'Lottery', Icon: LotteryIcon },
  { id: 'airdrop', label: 'Airdrop', Icon: AirdropIcon },
  { id: 'trader', label: 'AI Trader', Icon: TraderIcon },
  { id: 'referral', label: 'Invite', Icon: ReferralIcon },
  { id: 'settings', label: 'Settings', Icon: SettingsIcon },
];

export function NavTabs({ activeTab, onTabChange }: NavTabsProps) {
  const handleKeyDown = (e: React.KeyboardEvent, tabId: TabId) => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      onTabChange(tabId);
    } else if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
      e.preventDefault();
      const currentIndex = tabs.findIndex(t => t.id === activeTab);
      const nextIndex = e.key === 'ArrowRight' 
        ? (currentIndex + 1) % tabs.length
        : (currentIndex - 1 + tabs.length) % tabs.length;
      onTabChange(tabs[nextIndex].id);
    }
  };

  return (
    <nav className={styles.nav} role="tablist" aria-label="Main navigation">
      <div className={styles.navContent}>
        {tabs.map((tab) => {
          const isActive = activeTab === tab.id;
          return (
            <button
              key={tab.id}
              className={`${styles.tab} ${isActive ? styles.active : ''}`}
              onClick={() => onTabChange(tab.id)}
              onKeyDown={(e) => handleKeyDown(e, tab.id)}
              role="tab"
              aria-selected={isActive}
              aria-controls={`tabpanel-${tab.id}`}
              id={`tab-${tab.id}`}
              tabIndex={isActive ? 0 : -1}
            >
              <div className={styles.iconWrapper}>
                <tab.Icon 
                  size={22} 
                  color={isActive ? 'var(--brand-primary)' : 'var(--text-muted)'} 
                  className={styles.icon}
                  aria-hidden="true"
                />
                {isActive && <div className={styles.activeIndicator} aria-hidden="true" />}
              </div>
              <span className={styles.label}>{tab.label}</span>
            </button>
          );
        })}
      </div>
    </nav>
  );
}
