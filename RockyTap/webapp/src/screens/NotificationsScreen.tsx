import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, LoadingScreen, ErrorState, EmptyState, useToast, PullToRefresh } from '../components/ui';
import { BellIcon, CheckIcon } from '../components/Icons';
import { getNotifications, markNotificationRead, markAllNotificationsRead, Notification } from '../api/client';
import { getFriendlyErrorMessage } from '../lib/errorMessages';
import { hapticFeedback } from '../lib/telegram';
import styles from './NotificationsScreen.module.css';

export function NotificationsScreen() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [filter, setFilter] = useState<'all' | 'unread'>('all');
  const { showError: showToastError, showSuccess } = useToast();

  useEffect(() => {
    loadNotifications();
  }, [filter]);

  const loadNotifications = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await getNotifications({ unread_only: filter === 'unread' });
      setNotifications(response.notifications || []);
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      setError(errorMessage);
      showToastError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleMarkRead = async (notificationId: number) => {
    try {
      await markNotificationRead(notificationId);
      hapticFeedback('light');
      setNotifications((prev) =>
        prev.map((n) =>
          n.id === notificationId ? { ...n, read: true } : n
        )
      );
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      showToastError(errorMessage);
    }
  };

  const handleMarkAllRead = async () => {
    try {
      await markAllNotificationsRead();
      hapticFeedback('medium');
      showSuccess('All notifications marked as read');
      await loadNotifications();
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      showToastError(errorMessage);
    }
  };

  const unreadCount = notifications.filter((n) => !n.read).length;
  const filteredNotifications = filter === 'unread'
    ? notifications.filter((n) => !n.read)
    : notifications;

  if (loading && notifications.length === 0) {
    return <LoadingScreen message="Loading notifications..." />;
  }

  if (error && notifications.length === 0) {
    return <ErrorState message={error} onRetry={loadNotifications} />;
  }

  return (
    <PullToRefresh onRefresh={loadNotifications}>
      <div className={styles.container}>
        {/* Header */}
        <div className={styles.header}>
          <div className={styles.headerContent}>
            <BellIcon size={24} color="var(--brand-primary)" />
            <h1 className={styles.title}>Notifications</h1>
            {unreadCount > 0 && (
              <span className={styles.badge}>{unreadCount}</span>
            )}
          </div>
        </div>

        {/* Filters */}
        <div className={styles.filters}>
          <button
            className={`${styles.filterButton} ${filter === 'all' ? styles.active : ''}`}
            onClick={() => setFilter('all')}
          >
            All
          </button>
          <button
            className={`${styles.filterButton} ${filter === 'unread' ? styles.active : ''}`}
            onClick={() => setFilter('unread')}
          >
            Unread {unreadCount > 0 && `(${unreadCount})`}
          </button>
          {unreadCount > 0 && (
            <Button
              variant="ghost"
              size="sm"
              onClick={handleMarkAllRead}
              className={styles.markAllButton}
            >
              <CheckIcon size={16} />
              Mark All Read
            </Button>
          )}
        </div>

        {/* Notifications List */}
        {filteredNotifications.length === 0 ? (
          <EmptyState
            icon={<BellIcon size={48} color="var(--text-muted)" />}
            message={
              filter === 'unread'
                ? 'No unread notifications. You\'re all caught up!'
                : 'No notifications. You don\'t have any notifications yet.'
            }
          />
        ) : (
          <div className={styles.notificationsList}>
            {filteredNotifications.map((notification) => (
              <Card
                key={notification.id}
                variant={notification.read ? 'default' : 'elevated'}
                className={`${styles.notificationCard} ${!notification.read ? styles.unread : ''}`}
                onClick={() => !notification.read && handleMarkRead(notification.id)}
              >
                <CardContent>
                  <div className={styles.notificationHeader}>
                    <h3 className={styles.notificationTitle}>{notification.title}</h3>
                    {!notification.read && <span className={styles.unreadDot} />}
                  </div>
                  <p className={styles.notificationMessage}>{notification.message}</p>
                  <div className={styles.notificationFooter}>
                    <span className={styles.notificationTime}>
                      {formatNotificationTime(notification.created_at)}
                    </span>
                    {notification.type && (
                      <span className={styles.notificationType}>{notification.type}</span>
                    )}
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>
    </PullToRefresh>
  );
}

function formatNotificationTime(timestamp: string): string {
  const date = new Date(timestamp);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  return date.toLocaleDateString();
}

