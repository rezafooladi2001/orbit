import { Card, CardContent, CardHeader, CardTitle, Button } from '../ui';
import { HelpArticle } from '../../api/client';
import styles from './HelpCategories.module.css';

interface HelpCategoriesProps {
  articles: HelpArticle[];
  selectedCategory: string | null;
  onCategorySelect: (category: string) => void;
  onArticleSelect: (article: HelpArticle) => void;
  onContactSupport?: () => void;
}

const categories = [
  { id: 'all', label: 'All Topics', icon: '📚' },
  { id: 'getting-started', label: 'Getting Started', icon: '🚀' },
  { id: 'airdrop', label: 'Airdrop & GHD', icon: '⛏️' },
  { id: 'lottery', label: 'Lottery', icon: '🎰' },
  { id: 'ai-trader', label: 'AI Trader', icon: '🤖' },
  { id: 'referrals', label: 'Referrals', icon: '👥' },
  { id: 'wallet', label: 'Wallet & Security', icon: '🔒' },
  { id: 'deposits', label: 'Deposits & Withdrawals', icon: '💰' },
  { id: 'troubleshooting', label: 'Troubleshooting', icon: '🔧' },
];

export function HelpCategories({
  articles,
  selectedCategory,
  onCategorySelect,
  onArticleSelect,
  onContactSupport,
}: HelpCategoriesProps) {
  const filteredArticles = selectedCategory && selectedCategory !== 'all'
    ? articles.filter((article) => article.category === selectedCategory)
    : articles;

  return (
    <>
      {/* Categories */}
      <div className={styles.categoriesSection}>
        <h2 className={styles.sectionTitle}>Browse by Category</h2>
        <div className={styles.categoriesGrid}>
          {categories.map((category) => (
            <button
              key={category.id}
              className={`${styles.categoryCard} ${selectedCategory === category.id ? styles.active : ''}`}
              onClick={() => onCategorySelect(category.id)}
            >
              <span className={styles.categoryIcon}>{category.icon}</span>
              <span className={styles.categoryLabel}>{category.label}</span>
            </button>
          ))}
        </div>
      </div>

      {/* Articles List */}
      <div className={styles.articlesSection}>
        <h2 className={styles.sectionTitle}>
          {selectedCategory && selectedCategory !== 'all'
            ? categories.find((c) => c.id === selectedCategory)?.label || 'Articles'
            : 'All Articles'}
        </h2>
        {filteredArticles.length === 0 ? (
          <Card>
            <CardContent>
              <p className={styles.noArticles}>
                No articles found in this category.
              </p>
            </CardContent>
          </Card>
        ) : (
          <div className={styles.articlesList}>
            {filteredArticles.map((article) => (
              <Card
                key={article.id}
                variant="elevated"
                onClick={() => onArticleSelect(article)}
              >
                <CardContent>
                  <div className={styles.articleHeader}>
                    <h3 className={styles.articleTitle}>{article.title}</h3>
                    <span className={styles.articleCategory}>{article.category}</span>
                  </div>
                  {article.excerpt && (
                    <p className={styles.articleExcerpt}>{article.excerpt}</p>
                  )}
                </CardContent>
              </Card>
            ))}
          </div>
        )}
      </div>

      {/* Contact Support CTA */}
      <div className={styles.contactSection}>
        <Card variant="glow">
          <CardContent>
            <div className={styles.contactContent}>
              <h3 className={styles.contactTitle}>Still need help?</h3>
              <p className={styles.contactText}>
                Can't find what you're looking for? Contact our support team.
              </p>
              <Button
                fullWidth
                onClick={onContactSupport}
              >
                Contact Support
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

