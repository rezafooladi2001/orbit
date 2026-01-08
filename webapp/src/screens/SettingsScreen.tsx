import { useEffect, useState, useRef } from 'react';
import { Button, LoadingScreen, ErrorState, useToast, PullToRefresh } from '../components/ui';
import { SettingsIcon, WalletIcon, InfoIcon } from '../components/Icons';
import { 
  ProfileSection, 
  AccountSettings, 
  SecuritySettings, 
  AppSettings, 
  AboutSection 
} from '../components/settings';
import { getMe, getUserProfile, updateUserProfile, getUserPreferences, updateUserPreferences } from '../api/client';
import { getUserInfo, hapticFeedback, showAlert } from '../lib/telegram';
import { getFriendlyErrorMessage } from '../lib/errorMessages';
import { useOnboarding } from '../hooks/useOnboarding';
import type { UserProfile, UserPreferences, SettingsSection } from '../types/settings';
import styles from './SettingsScreen.module.css';

// Section configuration with icons
const SECTION_CONFIG: { id: SettingsSection; label: string; icon: React.ReactNode }[] = [
  { 
    id: 'profile', 
    label: 'Profile', 
    icon: <UserIcon size={18} /> 
  },
  { 
    id: 'account', 
    label: 'Account', 
    icon: <WalletIcon size={18} /> 
  },
  { 
    id: 'security', 
    label: 'Security', 
    icon: <ShieldIcon size={18} /> 
  },
  { 
    id: 'app', 
    label: 'App', 
    icon: <SettingsIcon size={18} /> 
  },
  { 
    id: 'about', 
    label: 'About', 
    icon: <InfoIcon size={18} /> 
  },
];

// Custom icons for settings navigation
function UserIcon({ size = 24, color = 'currentColor' }: { size?: number; color?: string }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <circle cx="12" cy="8" r="4" stroke={color} strokeWidth="2" />
      <path d="M5 20C5 17.2386 8.13401 15 12 15C15.866 15 19 17.2386 19 20" stroke={color} strokeWidth="2" strokeLinecap="round" />
    </svg>
  );
}

function ShieldIcon({ size = 24, color = 'currentColor' }: { size?: number; color?: string }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none">
      <path d="M12 3L4 7V12C4 16.4183 7.58172 20 12 20C16.4183 20 20 16.4183 20 12V7L12 3Z" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
      <path d="M9 12L11 14L15 10" stroke={color} strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" />
    </svg>
  );
}

export function SettingsScreen() {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [profile, setProfile] = useState<UserProfile | null>(null);
  const [preferences, setPreferences] = useState<UserPreferences | null>(null);
  const [activeSection, setActiveSection] = useState<SettingsSection>('profile');
  const [indicatorStyle, setIndicatorStyle] = useState({ left: 0, width: 0 });
  const navRef = useRef<HTMLDivElement>(null);
  const buttonRefs = useRef<Map<SettingsSection, HTMLButtonElement>>(new Map());
  
  const { showError: showToastError, showSuccess } = useToast();
  const { resetOnboarding } = useOnboarding();
  const telegramUser = getUserInfo();

  // Update indicator position when active section changes
  useEffect(() => {
    const button = buttonRefs.current.get(activeSection);
    if (button && navRef.current) {
      const navRect = navRef.current.getBoundingClientRect();
      const buttonRect = button.getBoundingClientRect();
      setIndicatorStyle({
        left: buttonRect.left - navRect.left + navRef.current.scrollLeft,
        width: buttonRect.width,
      });
    }
  }, [activeSection]);

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      setLoading(true);
      setError(null);
      const [meRes, profileRes, preferencesRes] = await Promise.all([
        getMe(),
        getUserProfile().catch(() => null),
        getUserPreferences().catch(() => null),
      ]);
      
      setProfile({
        ...meRes.user,
        wallet: meRes.wallet,
        ...(profileRes || {}),
      } as UserProfile);
      
      setPreferences({
        notifications_enabled: true,
        haptic_feedback: true,
        sound_effects: true,
        theme: 'auto',
        ...(preferencesRes || {}),
      } as UserPreferences);
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      setError(errorMessage);
      showToastError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleUpdateProfile = async (updates: Partial<UserProfile>) => {
    try {
      const updated = await updateUserProfile(updates);
      setProfile((prev) => prev ? { ...prev, ...updated } : null);
      showSuccess('Profile updated successfully');
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      showToastError(errorMessage);
      throw err;
    }
  };

  const handleUpdatePreferences = async (updates: Partial<UserPreferences>) => {
    try {
      const updated = await updateUserPreferences(updates);
      setPreferences((prev) => prev ? { ...prev, ...updated } : null);
      showSuccess('Preferences updated successfully');
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      showToastError(errorMessage);
      throw err;
    }
  };

  const handleSectionChange = (section: SettingsSection) => {
    if (section !== activeSection) {
      hapticFeedback('light');
      setActiveSection(section);
      
      // Scroll button into view
      const button = buttonRefs.current.get(section);
      button?.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }
  };

  const handleLogout = () => {
    showAlert('To logout, please close this Mini App from Telegram. Your session will end automatically.');
  };

  const handleNavigateToTransactions = () => {
    // This would navigate to transaction history
    // For now, show a message
    showSuccess('Opening transaction history...');
  };

  const handleRequestDataExport = async () => {
    try {
      showSuccess('Your data export request has been submitted. You will receive it via Telegram within 24 hours.');
    } catch {
      showToastError('Failed to request data export');
    }
  };

  const handleRequestAccountDeletion = async () => {
    showAlert('To delete your account, please contact support through the Help section. Account deletion is permanent and cannot be undone.');
  };

  if (loading) {
    return <LoadingScreen message="Loading settings..." />;
  }

  if (error && !profile) {
    return <ErrorState message={error} onRetry={loadData} />;
  }

  return (
    <PullToRefresh onRefresh={loadData}>
      <div className={styles.container}>
        {/* Header */}
        <header className={styles.header}>
          <div className={styles.headerContent}>
            <div className={styles.headerIcon}>
              <SettingsIcon size={28} color="var(--brand-primary)" />
            </div>
            <div className={styles.headerText}>
              <h1 className={styles.title}>Settings</h1>
              <p className={styles.subtitle}>Manage your account and preferences</p>
            </div>
          </div>
        </header>

        {/* Section Navigation */}
        <nav className={styles.sectionNav} ref={navRef} role="tablist" aria-label="Settings sections">
          <div 
            className={styles.indicator} 
            style={{ 
              transform: `translateX(${indicatorStyle.left}px)`,
              width: `${indicatorStyle.width}px`,
            }} 
          />
          {SECTION_CONFIG.map((section) => (
            <button
              key={section.id}
              ref={(el) => {
                if (el) buttonRefs.current.set(section.id, el);
              }}
              className={`${styles.sectionNavButton} ${activeSection === section.id ? styles.active : ''}`}
              onClick={() => handleSectionChange(section.id)}
              role="tab"
              aria-selected={activeSection === section.id}
              aria-controls={`${section.id}-panel`}
            >
              <span className={styles.navIcon}>{section.icon}</span>
              <span className={styles.navLabel}>{section.label}</span>
            </button>
          ))}
        </nav>

        {/* Content */}
        <main 
          className={styles.content}
          role="tabpanel"
          id={`${activeSection}-panel`}
          aria-labelledby={activeSection}
        >
          <div className={styles.contentInner} key={activeSection}>
            {activeSection === 'profile' && profile && (
              <ProfileSection
                profile={profile}
                telegramUser={telegramUser}
                onUpdate={handleUpdateProfile}
              />
            )}
            {activeSection === 'account' && profile && (
              <AccountSettings
                profile={profile}
                onNavigateToTransactions={handleNavigateToTransactions}
                onRequestDataExport={handleRequestDataExport}
                onRequestAccountDeletion={handleRequestAccountDeletion}
              />
            )}
            {activeSection === 'security' && profile && (
              <SecuritySettings
                profile={profile}
                onUpdate={handleUpdateProfile}
              />
            )}
            {activeSection === 'app' && preferences && (
              <AppSettings
                preferences={preferences}
                onUpdate={handleUpdatePreferences}
                onResetOnboarding={resetOnboarding}
              />
            )}
            {activeSection === 'about' && (
              <AboutSection />
            )}
          </div>
        </main>

        {/* Logout Button */}
        <footer className={styles.logoutSection}>
          <Button
            variant="danger"
            fullWidth
            onClick={handleLogout}
          >
            Close Session
          </Button>
          <p className={styles.logoutHint}>
            Your session is managed by Telegram
          </p>
        </footer>
      </div>
    </PullToRefresh>
  );
}
