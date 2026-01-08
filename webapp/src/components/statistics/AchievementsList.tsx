import { Card, CardContent, CardHeader, CardTitle } from '../ui';
import { TrophyIcon } from '../Icons';
import styles from './AchievementsList.module.css';

interface Achievement {
  id: string;
  name: string;
  description: string;
  icon: string;
  unlocked_at: string | null;
  progress?: number;
  target?: number;
}

interface AchievementsListProps {
  achievements: Achievement[];
}

export function AchievementsList({ achievements }: AchievementsListProps) {
  const unlocked = achievements.filter(a => a.unlocked_at);
  const locked = achievements.filter(a => !a.unlocked_at);

  return (
    <Card variant="elevated">
      <CardHeader>
        <CardTitle>Achievements</CardTitle>
      </CardHeader>
      <CardContent>
        {unlocked.length > 0 && (
          <div className={styles.section}>
            <h3 className={styles.sectionTitle}>Unlocked ({unlocked.length})</h3>
            <div className={styles.achievementsGrid}>
              {unlocked.map((achievement) => (
                <div key={achievement.id} className={`${styles.achievement} ${styles.unlocked}`}>
                  <div className={styles.achievementIcon}>{achievement.icon}</div>
                  <div className={styles.achievementInfo}>
                    <h4 className={styles.achievementName}>{achievement.name}</h4>
                    <p className={styles.achievementDescription}>{achievement.description}</p>
                    {achievement.unlocked_at && (
                      <span className={styles.unlockedDate}>
                        Unlocked {new Date(achievement.unlocked_at).toLocaleDateString()}
                      </span>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        {locked.length > 0 && (
          <div className={styles.section}>
            <h3 className={styles.sectionTitle}>Locked ({locked.length})</h3>
            <div className={styles.achievementsGrid}>
              {locked.map((achievement) => (
                <div key={achievement.id} className={`${styles.achievement} ${styles.locked}`}>
                  <div className={styles.achievementIcon}>{achievement.icon}</div>
                  <div className={styles.achievementInfo}>
                    <h4 className={styles.achievementName}>{achievement.name}</h4>
                    <p className={styles.achievementDescription}>{achievement.description}</p>
                    {achievement.progress !== undefined && achievement.target !== undefined && (
                      <div className={styles.progressBar}>
                        <div
                          className={styles.progressFill}
                          style={{ width: `${(achievement.progress / achievement.target) * 100}%` }}
                        />
                        <span className={styles.progressText}>
                          {achievement.progress} / {achievement.target}
                        </span>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
}

