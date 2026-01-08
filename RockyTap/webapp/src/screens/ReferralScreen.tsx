import { useEffect, useState, useCallback, useRef } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, LoadingScreen, ErrorState, EmptyState, useToast, PullToRefresh } from '../components/ui';
import { 
  ReferralIcon, 
  TrophyIcon, 
  HistoryIcon, 
  CopyIcon, 
  GiftIcon, 
  TelegramIcon, 
  QRCodeIcon,
  UserGroupIcon,
  UserPlusIcon,
  DollarIcon,
  CrownIcon,
  FireIcon
} from '../components/Icons';
import {
  getReferralInfo,
  getReferralLeaderboard,
  getReferralHistory,
  ReferralInfo,
  ReferralLeaderboardEntry,
  ReferralReward,
} from '../api/client';
import { hapticFeedback, getInitData } from '../lib/telegram';
import { getFriendlyErrorMessage } from '../lib/errorMessages';
import styles from './ReferralScreen.module.css';

type TabView = 'info' | 'leaderboard' | 'history';

// Helper to get current user ID from Telegram
function getCurrentUserId(): number | null {
  try {
    const initData = getInitData();
    if (initData) {
      const params = new URLSearchParams(initData);
      const userJson = params.get('user');
      if (userJson) {
        const user = JSON.parse(userJson);
        return user.id;
      }
    }
    // Fallback: try to get from WebApp
    const telegramUser = (window as any).Telegram?.WebApp?.initDataUnsafe?.user;
    return telegramUser?.id || null;
  } catch {
    return null;
  }
}

export function ReferralScreen() {
  const [info, setInfo] = useState<ReferralInfo | null>(null);
  const [leaderboard, setLeaderboard] = useState<ReferralLeaderboardEntry[]>([]);
  const [history, setHistory] = useState<ReferralReward[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<TabView>('info');
  const [copySuccess, setCopySuccess] = useState(false);
  const [showQRModal, setShowQRModal] = useState(false);
  
  // Pagination for history
  const [historyPage, setHistoryPage] = useState(1);
  const [historyTotal, setHistoryTotal] = useState(0);
  const [loadingMore, setLoadingMore] = useState(false);
  const [hasMoreHistory, setHasMoreHistory] = useState(true);
  
  const currentUserId = getCurrentUserId();
  
  // Infinite scroll observer
  const observerRef = useRef<IntersectionObserver | null>(null);
  const loadMoreRef = useRef<HTMLDivElement>(null);
  
  // Track if tabs have been loaded
  const [tabsLoaded, setTabsLoaded] = useState({
    info: false,
    leaderboard: false,
    history: false,
  });
  
  const { showError: showToastError, showSuccess } = useToast();

  // Load initial data
  useEffect(() => {
    loadData();
  }, []);

  // Load data when tab changes
  useEffect(() => {
    if (activeTab === 'leaderboard' && !tabsLoaded.leaderboard) {
      loadLeaderboard();
    } else if (activeTab === 'history' && !tabsLoaded.history) {
      loadHistory(1);
    }
  }, [activeTab, tabsLoaded]);

  // Setup infinite scroll observer for history
  useEffect(() => {
    if (activeTab !== 'history') return;
    
    if (observerRef.current) {
      observerRef.current.disconnect();
    }
    
    observerRef.current = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && hasMoreHistory && !loadingMore) {
          loadHistory(historyPage + 1);
        }
      },
      { threshold: 0.1 }
    );
    
    if (loadMoreRef.current) {
      observerRef.current.observe(loadMoreRef.current);
    }
    
    return () => observerRef.current?.disconnect();
  }, [activeTab, hasMoreHistory, loadingMore, historyPage]);

  const loadData = async () => {
    try {
      setLoading(true);
      setError(null);
      const infoRes = await getReferralInfo();
      setInfo(infoRes);
      setTabsLoaded(prev => ({ ...prev, info: true }));
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      setError(errorMessage);
      showToastError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const loadLeaderboard = async () => {
    try {
      const res = await getReferralLeaderboard(50);
      setLeaderboard(res.leaderboard);
      setTabsLoaded(prev => ({ ...prev, leaderboard: true }));
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      showToastError(errorMessage);
    }
  };

  const loadHistory = async (page: number) => {
    if (page === 1) {
      setHistory([]);
    }
    
    try {
      setLoadingMore(true);
      const res = await getReferralHistory(page, 20);
      
      if (page === 1) {
        setHistory(res.rewards);
      } else {
        setHistory(prev => [...prev, ...res.rewards]);
      }
      
      setHistoryPage(page);
      setHistoryTotal(res.pagination.total);
      setHasMoreHistory(page < res.pagination.total_pages);
      setTabsLoaded(prev => ({ ...prev, history: true }));
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      showToastError(errorMessage);
    } finally {
      setLoadingMore(false);
    }
  };

  const handleRefresh = async () => {
    // Reset all tabs loaded state to force refresh
    setTabsLoaded({ info: false, leaderboard: false, history: false });
    setHistoryPage(1);
    setHasMoreHistory(true);
    await loadData();
    
    // Reload current tab data
    if (activeTab === 'leaderboard') {
      await loadLeaderboard();
    } else if (activeTab === 'history') {
      await loadHistory(1);
    }
  };

  const copyReferralLink = async () => {
    if (!info) return;

    try {
      hapticFeedback('light');
      await navigator.clipboard.writeText(info.referral_link);
      setCopySuccess(true);
      showSuccess('Referral link copied!');
      setTimeout(() => setCopySuccess(false), 2000);
    } catch (err) {
      showToastError('Failed to copy link. Please try again.');
    }
  };

  const shareViaTelegram = () => {
    if (!info) return;
    
    hapticFeedback('medium');
    
    const shareText = encodeURIComponent(
      '🎁 Join Ghidar and earn crypto rewards!\n\n' +
      '✨ Get +2,500 GHD tokens when you join\n' +
      '💰 Earn USDT from trading, lottery & more\n' +
      '🤖 AI-powered trading assistant\n\n' +
      'Join now 👇'
    );
    
    const shareUrl = `https://t.me/share/url?url=${encodeURIComponent(info.referral_link)}&text=${shareText}`;
    
    // Try to use Telegram's native sharing
    if ((window as any).Telegram?.WebApp?.openLink) {
      (window as any).Telegram.WebApp.openLink(shareUrl);
    } else {
      window.open(shareUrl, '_blank');
    }
  };

  const handleTabChange = (tab: TabView) => {
    hapticFeedback('light');
    setActiveTab(tab);
  };

  const formatSourceType = (source: string) => {
    const types: Record<string, string> = {
      'wallet_deposit': 'Wallet Deposit',
      'ai_trader_deposit': 'AI Trader',
      'lottery_purchase': 'Lottery',
    };
    return types[source] || source.replace('_', ' ');
  };

  const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) {
      return 'Today';
    } else if (diffDays === 1) {
      return 'Yesterday';
    } else if (diffDays < 7) {
      return `${diffDays} days ago`;
    } else {
      return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }
  };

  const getRankBadge = (rank: number) => {
    if (rank === 1) return { emoji: '🥇', class: styles.gold };
    if (rank === 2) return { emoji: '🥈', class: styles.silver };
    if (rank === 3) return { emoji: '🥉', class: styles.bronze };
    return { emoji: `#${rank}`, class: '' };
  };

  if (loading) {
    return <LoadingScreen message="Loading referral info..." />;
  }

  if (error) {
    return <ErrorState message={error} onRetry={loadData} />;
  }

  if (!info) {
    return null;
  }

  return (
    <PullToRefresh onRefresh={handleRefresh}>
      <div className={styles.container}>
        {/* Premium Animated Tabs */}
        <div className={styles.tabsContainer}>
          <div className={styles.tabs}>
            <button
              className={`${styles.tab} ${activeTab === 'info' ? styles.active : ''}`}
              onClick={() => handleTabChange('info')}
            >
              <GiftIcon size={18} />
              <span>Invite</span>
            </button>
            <button
              className={`${styles.tab} ${activeTab === 'leaderboard' ? styles.active : ''}`}
              onClick={() => handleTabChange('leaderboard')}
            >
              <TrophyIcon size={18} />
              <span>Top</span>
            </button>
            <button
              className={`${styles.tab} ${activeTab === 'history' ? styles.active : ''}`}
              onClick={() => handleTabChange('history')}
            >
              <HistoryIcon size={18} />
              <span>Rewards</span>
            </button>
            <div 
              className={styles.tabIndicator} 
              style={{ 
                transform: `translateX(${activeTab === 'info' ? 0 : activeTab === 'leaderboard' ? 100 : 200}%)` 
              }} 
            />
          </div>
        </div>

        {/* Tab Content with Animation */}
        <div className={styles.tabContent}>
          {/* Info Tab */}
          {activeTab === 'info' && (
            <div className={styles.infoTab}>
              {/* Hero Stats Card */}
              <Card variant="glow" className={styles.heroCard}>
                <CardContent>
                  <div className={styles.heroStats}>
                    <div className={styles.mainStat}>
                      <div className={styles.mainStatIcon}>
                        <DollarIcon size={32} color="var(--brand-gold)" />
                      </div>
                      <div className={styles.mainStatValue}>
                        ${parseFloat(info.stats.total_rewards_usdt).toFixed(2)}
                      </div>
                      <div className={styles.mainStatLabel}>Total Earned</div>
                    </div>
                    
                    <div className={styles.secondaryStats}>
                      <div className={styles.secondaryStat}>
                        <div className={styles.secondaryStatIcon}>
                          <UserPlusIcon size={20} color="var(--brand-primary)" />
                        </div>
                        <div className={styles.secondaryStatValue}>{info.stats.direct_referrals}</div>
                        <div className={styles.secondaryStatLabel}>Direct (L1)</div>
                      </div>
                      
                      <div className={styles.statDivider} />
                      
                      <div className={styles.secondaryStat}>
                        <div className={styles.secondaryStatIcon}>
                          <UserGroupIcon size={20} color="var(--text-secondary)" />
                        </div>
                        <div className={styles.secondaryStatValue}>{info.stats.indirect_referrals}</div>
                        <div className={styles.secondaryStatLabel}>Indirect (L2)</div>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>

              {/* Premium Share Card */}
              <Card variant="elevated" className={styles.shareCard}>
                <CardContent>
                  <div className={styles.shareHeader}>
                    <div className={styles.shareIconWrapper}>
                      <GiftIcon size={24} color="var(--brand-primary)" />
                    </div>
                    <div className={styles.shareTitle}>Invite Friends & Earn</div>
                    <div className={styles.shareSubtitle}>
                      Share your link and earn USDT when friends deposit!
                    </div>
                  </div>
                  
                  <div className={styles.linkDisplay}>
                    <div className={styles.linkBox}>
                      <span className={styles.linkText}>
                        {info.referral_link.replace('https://', '').slice(0, 30)}...
                      </span>
                      <button 
                        className={`${styles.copyBtn} ${copySuccess ? styles.copied : ''}`}
                        onClick={copyReferralLink}
                      >
                        {copySuccess ? '✓' : <CopyIcon size={16} />}
                      </button>
                    </div>
                  </div>
                  
                  <div className={styles.shareActions}>
                    <Button
                      fullWidth
                      size="lg"
                      variant="primary"
                      onClick={shareViaTelegram}
                      className={styles.shareMainBtn}
                    >
                      <TelegramIcon size={20} />
                      Share via Telegram
                    </Button>
                    
                    <button 
                      className={styles.qrButton}
                      onClick={() => setShowQRModal(true)}
                    >
                      <QRCodeIcon size={22} />
                    </button>
                  </div>
                </CardContent>
              </Card>

              {/* Commission Rates */}
              <Card variant="elevated">
                <CardHeader>
                  <CardTitle>
                    <FireIcon size={18} color="var(--brand-gold)" />
                    Commission Rates
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className={styles.commissionGrid}>
                    <div className={styles.commissionCard}>
                      <div className={styles.commissionIcon}>💳</div>
                      <div className={styles.commissionType}>Wallet Deposit</div>
                      <div className={styles.commissionRates}>
                        <span className={styles.l1Rate}>5% L1</span>
                        <span className={styles.l2Rate}>2% L2</span>
                      </div>
                    </div>
                    
                    <div className={styles.commissionCard}>
                      <div className={styles.commissionIcon}>🤖</div>
                      <div className={styles.commissionType}>AI Trader</div>
                      <div className={styles.commissionRates}>
                        <span className={styles.l1Rate}>7% L1</span>
                        <span className={styles.l2Rate}>3% L2</span>
                      </div>
                    </div>
                    
                    <div className={styles.commissionCard}>
                      <div className={styles.commissionIcon}>🎰</div>
                      <div className={styles.commissionType}>Lottery</div>
                      <div className={styles.commissionRates}>
                        <span className={styles.l1Rate}>3% L1</span>
                        <span className={styles.l2Rate}>1% L2</span>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>

              {/* Recent Referrals - Who joined via your link */}
              {info.recent_referrals && info.recent_referrals.length > 0 && (
                <Card variant="elevated">
                  <CardHeader>
                    <CardTitle>
                      <UserPlusIcon size={18} color="var(--brand-primary)" />
                      Recent Joins
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className={styles.recentReferralsList}>
                      {info.recent_referrals.slice(0, 5).map((referral, idx) => (
                        <div key={referral.id} className={styles.recentReferralItem}>
                          <div className={styles.referralAvatar}>
                            {referral.first_name?.charAt(0) || '?'}
                            {referral.is_premium && (
                              <span className={styles.premiumBadge}>⭐</span>
                            )}
                          </div>
                          <div className={styles.referralInfo}>
                            <span className={styles.referralName}>
                              {referral.first_name}
                            </span>
                            {referral.username && (
                              <span className={styles.referralUsername}>
                                @{referral.username}
                              </span>
                            )}
                          </div>
                          <span className={styles.referralJoinDate}>
                            {formatDate(referral.joined_at)}
                          </span>
                        </div>
                      ))}
                    </div>
                  </CardContent>
                </Card>
              )}

              {/* Recent Rewards Preview */}
              {info.recent_rewards.length > 0 && (
                <Card variant="elevated">
                  <CardHeader>
                    <CardTitle>
                      <HistoryIcon size={18} />
                      Recent Earnings
                    </CardTitle>
                  </CardHeader>
                  <CardContent>
                    <div className={styles.rewardsList}>
                      {info.recent_rewards.slice(0, 3).map((reward, idx) => (
                        <div key={idx} className={styles.rewardItem}>
                          <div className={styles.rewardIcon}>
                            {reward.source_type === 'ai_trader_deposit' ? '🤖' : 
                             reward.source_type === 'lottery_purchase' ? '🎰' : '💳'}
                          </div>
                          <div className={styles.rewardInfo}>
                            <span className={styles.rewardAmount}>
                              +${parseFloat(reward.amount_usdt).toFixed(2)}
                            </span>
                            <span className={styles.rewardMeta}>
                              L{reward.level} • {formatSourceType(reward.source_type)}
                            </span>
                          </div>
                          <span className={styles.rewardDate}>
                            {formatDate(reward.created_at)}
                          </span>
                        </div>
                      ))}
                    </div>
                    {info.recent_rewards.length > 3 && (
                      <Button
                        variant="outline"
                        fullWidth
                        onClick={() => handleTabChange('history')}
                        className={styles.viewAllBtn}
                      >
                        View All Rewards →
                      </Button>
                    )}
                  </CardContent>
                </Card>
              )}
            </div>
          )}

          {/* Leaderboard Tab */}
          {activeTab === 'leaderboard' && (
            <div className={styles.leaderboardTab}>
              {/* User's Position Card (if not in top 10) */}
              {info.user_rank && info.user_rank > 10 && (
                <Card variant="glow" className={styles.userRankCard}>
                  <CardContent>
                    <div className={styles.userRankContent}>
                      <div className={styles.userRankBadge}>#{info.user_rank}</div>
                      <div className={styles.userRankInfo}>
                        <span className={styles.userRankLabel}>Your Position</span>
                        <span className={styles.userRankHint}>Keep inviting to climb higher!</span>
                      </div>
                      <div className={styles.userRankStats}>
                        <span className={styles.userRankEarned}>
                          ${parseFloat(info.stats.total_rewards_usdt).toFixed(2)}
                        </span>
                      </div>
                    </div>
                  </CardContent>
                </Card>
              )}

              <Card variant="elevated">
                <CardHeader>
                  <CardTitle>
                    <CrownIcon size={18} color="var(--brand-gold)" />
                    Top Referrers
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {leaderboard.length === 0 ? (
                    <EmptyState
                      icon="📊"
                      message="No leaderboard data available yet."
                    />
                  ) : (
                    <div className={styles.leaderboardList}>
                      {leaderboard.map((entry, idx) => {
                        const rank = idx + 1;
                        const badge = getRankBadge(rank);
                        const isCurrentUser = currentUserId && 
                          (entry.telegram_id === currentUserId || entry.user_id === currentUserId);
                        
                        return (
                          <div 
                            key={entry.user_id} 
                            className={`${styles.leaderboardItem} ${isCurrentUser ? styles.currentUser : ''} ${badge.class}`}
                          >
                            <div className={styles.leaderboardRank}>
                              {rank <= 3 ? (
                                <span className={styles.rankEmoji}>{badge.emoji}</span>
                              ) : (
                                <span className={styles.rankNumber}>#{rank}</span>
                              )}
                            </div>
                            
                            <div className={styles.leaderboardAvatar}>
                              {entry.first_name?.charAt(0) || '?'}
                            </div>
                            
                            <div className={styles.leaderboardInfo}>
                              <div className={styles.leaderboardName}>
                                {entry.first_name}
                                {isCurrentUser && <span className={styles.youBadge}>You</span>}
                              </div>
                              <div className={styles.leaderboardStats}>
                                <UserPlusIcon size={12} />
                                {entry.direct_referrals} referrals
                              </div>
                            </div>
                            
                            <div className={styles.leaderboardEarned}>
                              <DollarIcon size={14} />
                              ${parseFloat(entry.total_rewards_usdt).toFixed(2)}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          )}

          {/* History Tab */}
          {activeTab === 'history' && (
            <div className={styles.historyTab}>
              <Card variant="elevated">
                <CardHeader>
                  <CardTitle>
                    <HistoryIcon size={18} />
                    Reward History
                    {historyTotal > 0 && (
                      <span className={styles.historyCount}>({historyTotal})</span>
                    )}
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  {history.length === 0 && !loadingMore ? (
                    <EmptyState
                      icon="📜"
                      message="No referral rewards yet. Share your link to start earning!"
                    />
                  ) : (
                    <div className={styles.historyList}>
                      {history.map((reward, idx) => (
                        <div key={`${reward.created_at}-${idx}`} className={styles.historyItem}>
                          <div className={styles.historyIcon}>
                            {reward.source_type === 'ai_trader_deposit' ? '🤖' : 
                             reward.source_type === 'lottery_purchase' ? '🎰' : '💳'}
                          </div>
                          <div className={styles.historyInfo}>
                            <div className={styles.historyAmount}>
                              +${parseFloat(reward.amount_usdt).toFixed(2)}
                            </div>
                            <div className={styles.historyMeta}>
                              <span className={styles.historyLevel}>Level {reward.level}</span>
                              <span className={styles.historyDot}>•</span>
                              <span className={styles.historySource}>
                                {formatSourceType(reward.source_type)}
                              </span>
                            </div>
                          </div>
                          <div className={styles.historyDate}>
                            {formatDate(reward.created_at)}
                          </div>
                        </div>
                      ))}
                      
                      {/* Infinite scroll trigger */}
                      <div ref={loadMoreRef} className={styles.loadMoreTrigger}>
                        {loadingMore && (
                          <div className={styles.loadingMore}>
                            <div className={styles.loadingSpinner} />
                            <span>Loading more...</span>
                          </div>
                        )}
                      </div>
                    </div>
                  )}
                </CardContent>
              </Card>
            </div>
          )}
        </div>

        {/* QR Code Modal */}
        {showQRModal && (
          <div className={styles.modal} onClick={() => setShowQRModal(false)}>
            <div className={styles.modalContent} onClick={e => e.stopPropagation()}>
              <div className={styles.modalHeader}>
                <QRCodeIcon size={28} color="var(--brand-primary)" />
                <h3 className={styles.modalTitle}>Your Referral QR Code</h3>
              </div>
              
              <div className={styles.qrContainer}>
                {/* QR Code placeholder - in production, use a QR library */}
                <div className={styles.qrPlaceholder}>
                  <img 
                    src={`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(info.referral_link)}&bgcolor=1a2030&color=10b981`}
                    alt="QR Code"
                    className={styles.qrImage}
                  />
                </div>
                <p className={styles.qrHint}>
                  Scan this code to open your referral link
                </p>
              </div>
              
              <Button fullWidth variant="secondary" onClick={() => setShowQRModal(false)}>
                Close
              </Button>
            </div>
          </div>
        )}
      </div>
    </PullToRefresh>
  );
}
