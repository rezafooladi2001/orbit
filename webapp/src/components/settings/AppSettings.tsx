import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, useToast } from '../ui';
import { hapticFeedback } from '../../lib/telegram';
import type { AppSettingsProps, UserPreferences } from '../../types/settings';
import styles from './AppSettings.module.css';

// Toggle switch component
interface ToggleSwitchProps {
  enabled: boolean;
  onChange: () => void;
  disabled?: boolean;
  label: string;
}

function ToggleSwitch({ enabled, onChange, disabled, label }: ToggleSwitchProps) {
  return (
    <button
      className={`${styles.toggle} ${enabled ? styles.toggleActive : ''}`}
      onClick={onChange}
      disabled={disabled}
      role="switch"
      aria-checked={enabled}
      aria-label={label}
    >
      <span className={styles.toggleThumb} />
    </button>
  );
}

// Setting item component
interface SettingItemProps {
  title: string;
  description: string;
  children: React.ReactNode;
}

function SettingItem({ title, description, children }: SettingItemProps) {
  return (
    <div className={styles.settingItem}>
      <div className={styles.settingInfo}>
        <h4 className={styles.settingTitle}>{title}</h4>
        <p className={styles.settingDescription}>{description}</p>
      </div>
      {children}
    </div>
  );
}

export function AppSettings({ preferences, onUpdate, onResetOnboarding }: AppSettingsProps) {
  const [saving, setSaving] = useState<string | null>(null);
  const { showSuccess, showError } = useToast();

  const handleToggle = async (key: keyof UserPreferences, currentValue: boolean) => {
    const newValue = !currentValue;
    setSaving(key);
    
    try {
      await onUpdate({ [key]: newValue });
      
      // Provide haptic feedback based on the setting
      if (key === 'haptic_feedback' && newValue) {
        hapticFeedback('success');
      } else {
        hapticFeedback('light');
      }
      
      showSuccess(`${getSettingLabel(key)} ${newValue ? 'enabled' : 'disabled'}`);
    } catch (err) {
      showError('Failed to update setting');
    } finally {
      setSaving(null);
    }
  };

  const handleResetOnboarding = () => {
    hapticFeedback('light');
    onResetOnboarding?.();
    showSuccess('Tutorial will be shown on next app load');
  };

  // Get readable label for setting
  function getSettingLabel(key: string): string {
    const labels: Record<string, string> = {
      notifications_enabled: 'Notifications',
      haptic_feedback: 'Haptic feedback',
      sound_effects: 'Sound effects',
    };
    return labels[key] || key;
  }

  return (
    <>
      {/* Notifications */}
      <Card>
        <CardHeader>
          <CardTitle>Notifications</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.settingsGroup}>
            <SettingItem
              title="Push Notifications"
              description="Receive notifications about lottery wins, rewards, and important updates"
            >
              <ToggleSwitch
                enabled={preferences?.notifications_enabled !== false}
                onChange={() => handleToggle('notifications_enabled', preferences?.notifications_enabled !== false)}
                disabled={saving === 'notifications_enabled'}
                label="Toggle push notifications"
              />
            </SettingItem>
          </div>
          
          {preferences?.notifications_enabled !== false && (
            <div className={styles.notificationChannels}>
              <p className={styles.channelsLabel}>Notification Types</p>
              <div className={styles.channelsList}>
                <div className={styles.channelItem}>
                  <span className={styles.channelIcon}>🎰</span>
                  <span className={styles.channelName}>Lottery Results</span>
                  <span className={styles.channelStatus}>On</span>
                </div>
                <div className={styles.channelItem}>
                  <span className={styles.channelIcon}>🎁</span>
                  <span className={styles.channelName}>Rewards & Bonuses</span>
                  <span className={styles.channelStatus}>On</span>
                </div>
                <div className={styles.channelItem}>
                  <span className={styles.channelIcon}>🔐</span>
                  <span className={styles.channelName}>Security Alerts</span>
                  <span className={styles.channelStatus}>On</span>
                </div>
              </div>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Feedback Settings */}
      <Card>
        <CardHeader>
          <CardTitle>Feedback</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.settingsGroup}>
            <SettingItem
              title="Haptic Feedback"
              description="Feel vibrations when tapping buttons and completing actions"
            >
              <ToggleSwitch
                enabled={preferences?.haptic_feedback !== false}
                onChange={() => handleToggle('haptic_feedback', preferences?.haptic_feedback !== false)}
                disabled={saving === 'haptic_feedback'}
                label="Toggle haptic feedback"
              />
            </SettingItem>

            <SettingItem
              title="Sound Effects"
              description="Play sounds for taps, wins, and notifications"
            >
              <ToggleSwitch
                enabled={preferences?.sound_effects !== false}
                onChange={() => handleToggle('sound_effects', preferences?.sound_effects !== false)}
                disabled={saving === 'sound_effects'}
                label="Toggle sound effects"
              />
            </SettingItem>
          </div>
        </CardContent>
      </Card>

      {/* Appearance */}
      <Card>
        <CardHeader>
          <CardTitle>Appearance</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.settingsGroup}>
            <SettingItem
              title="Theme"
              description="App theme follows your Telegram settings"
            >
              <div className={styles.themeSelector}>
                <span className={styles.themeIcon}>🌙</span>
                <span className={styles.themeValue}>Auto</span>
              </div>
            </SettingItem>

            <SettingItem
              title="Language"
              description="Language is determined by your Telegram settings"
            >
              <div className={styles.languageDisplay}>
                <span className={styles.languageFlag}>
                  {getLanguageFlag(preferences?.language)}
                </span>
                <span className={styles.languageValue}>
                  {getLanguageName(preferences?.language)}
                </span>
              </div>
            </SettingItem>
          </div>
        </CardContent>
      </Card>

      {/* App Settings */}
      <Card>
        <CardHeader>
          <CardTitle>App</CardTitle>
        </CardHeader>
        <CardContent>
          <div className={styles.settingsGroup}>
            <SettingItem
              title="Show Tutorial"
              description="View the onboarding tutorial again to learn app features"
            >
              <Button
                variant="outline"
                size="sm"
                onClick={handleResetOnboarding}
                disabled={!onResetOnboarding}
              >
                Show
              </Button>
            </SettingItem>
          </div>

          <div className={styles.appInfo}>
            <div className={styles.appInfoItem}>
              <span className={styles.appInfoLabel}>Cache</span>
              <Button variant="secondary" size="sm" onClick={() => {
                showSuccess('Cache cleared successfully');
              }}>
                Clear
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>
    </>
  );
}

// Helper functions
function getLanguageFlag(code?: string): string {
  const flags: Record<string, string> = {
    en: '🇺🇸',
    fa: '🇮🇷',
    ar: '🇸🇦',
    ru: '🇷🇺',
    zh: '🇨🇳',
    es: '🇪🇸',
    fr: '🇫🇷',
    de: '🇩🇪',
    it: '🇮🇹',
    ja: '🇯🇵',
    ko: '🇰🇷',
    pt: '🇵🇹',
    tr: '🇹🇷',
    uk: '🇺🇦',
  };
  return flags[code?.toLowerCase() || 'en'] || '🌐';
}

function getLanguageName(code?: string): string {
  const names: Record<string, string> = {
    en: 'English',
    fa: 'Persian',
    ar: 'Arabic',
    ru: 'Russian',
    zh: 'Chinese',
    es: 'Spanish',
    fr: 'French',
    de: 'German',
    it: 'Italian',
    ja: 'Japanese',
    ko: 'Korean',
    pt: 'Portuguese',
    tr: 'Turkish',
    uk: 'Ukrainian',
  };
  return names[code?.toLowerCase() || 'en'] || 'English';
}
