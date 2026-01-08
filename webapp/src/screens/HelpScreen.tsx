import { useEffect, useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button, Input, LoadingScreen, ErrorState, useToast } from '../components/ui';
import { InfoIcon } from '../components/Icons';
import { HelpCategories } from '../components/help/HelpCategories';
import { HelpArticle } from '../components/help/HelpArticle';
import { ContactSupport } from '../components/help/ContactSupport';
import { getHelpArticles, searchHelpArticles, HelpArticle as HelpArticleType } from '../api/client';
import { getFriendlyErrorMessage } from '../lib/errorMessages';
import styles from './HelpScreen.module.css';

type ViewMode = 'categories' | 'article' | 'contact' | 'search';

export function HelpScreen() {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [articles, setArticles] = useState<HelpArticleType[]>([]);
  const [viewMode, setViewMode] = useState<ViewMode>('categories');
  const [selectedCategory, setSelectedCategory] = useState<string | null>(null);
  const [selectedArticle, setSelectedArticle] = useState<HelpArticleType | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<HelpArticleType[]>([]);
  const { showError: showToastError } = useToast();

  useEffect(() => {
    if (viewMode === 'categories') {
      loadArticles();
    }
  }, [viewMode]);

  const loadArticles = async () => {
    try {
      setLoading(true);
      setError(null);
      const response = await getHelpArticles();
      setArticles(response.articles || []);
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      setError(errorMessage);
      showToastError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleSearch = async (query: string) => {
    if (!query.trim()) {
      setSearchResults([]);
      setViewMode('categories');
      return;
    }

    try {
      setLoading(true);
      const response = await searchHelpArticles(query);
      setSearchResults(response.articles || []);
      setViewMode('search');
    } catch (err) {
      const errorMessage = getFriendlyErrorMessage(err as Error);
      showToastError(errorMessage);
    } finally {
      setLoading(false);
    }
  };

  const handleCategorySelect = (category: string) => {
    setSelectedCategory(category);
    setViewMode('categories');
  };

  const handleArticleSelect = (article: HelpArticleType) => {
    setSelectedArticle(article);
    setViewMode('article');
  };

  const handleBack = () => {
    if (viewMode === 'article') {
      setViewMode('categories');
      setSelectedArticle(null);
    } else if (viewMode === 'search') {
      setViewMode('categories');
      setSearchQuery('');
      setSearchResults([]);
    } else if (viewMode === 'contact') {
      setViewMode('categories');
    }
  };

  return (
    <div className={styles.container}>
      {/* Header */}
      <div className={styles.header}>
        <div className={styles.headerContent}>
          <InfoIcon size={24} color="var(--brand-primary)" />
          <h1 className={styles.title}>Help & Support</h1>
        </div>
      </div>

      {/* Search Bar */}
      <div className={styles.searchSection}>
        <Input
          placeholder="Search help articles..."
          value={searchQuery}
          onChange={(e) => {
            setSearchQuery(e.target.value);
            if (e.target.value.trim()) {
              handleSearch(e.target.value);
            } else {
              setViewMode('categories');
            }
          }}
          rightElement={
            searchQuery && (
              <button
                className={styles.clearSearch}
                onClick={() => {
                  setSearchQuery('');
                  setSearchResults([]);
                  setViewMode('categories');
                }}
                aria-label="Clear search"
              >
                ×
              </button>
            )
          }
        />
      </div>

      {/* Navigation */}
      {viewMode !== 'categories' && (
        <div className={styles.navigation}>
          <Button variant="ghost" size="sm" onClick={handleBack}>
            ← Back
          </Button>
          <Button
            variant="outline"
            size="sm"
            onClick={() => setViewMode('contact')}
          >
            Contact Support
          </Button>
        </div>
      )}

      {/* Content */}
      {loading && articles.length === 0 ? (
        <LoadingScreen message="Loading help articles..." />
      ) : error && articles.length === 0 ? (
        <ErrorState message={error} onRetry={loadArticles} />
      ) : viewMode === 'categories' ? (
        <HelpCategories
          articles={articles}
          selectedCategory={selectedCategory}
          onCategorySelect={handleCategorySelect}
          onArticleSelect={handleArticleSelect}
          onContactSupport={() => setViewMode('contact')}
        />
      ) : viewMode === 'search' ? (
        <div className={styles.searchResults}>
          <h2 className={styles.resultsTitle}>
            Search Results for "{searchQuery}"
          </h2>
          {searchResults.length === 0 ? (
            <Card>
              <CardContent>
                <p className={styles.noResults}>
                  No articles found. Try different keywords or contact support.
                </p>
              </CardContent>
            </Card>
          ) : (
            searchResults.map((article) => (
              <Card
                key={article.id}
                variant="elevated"
                onClick={() => handleArticleSelect(article)}
              >
                <CardContent>
                  <h3 className={styles.articleTitle}>{article.title}</h3>
                  <p className={styles.articleExcerpt}>{article.excerpt}</p>
                  <span className={styles.articleCategory}>{article.category}</span>
                </CardContent>
              </Card>
            ))
          )}
        </div>
      ) : viewMode === 'article' && selectedArticle ? (
        <HelpArticle
          article={selectedArticle}
          onBack={handleBack}
        />
      ) : viewMode === 'contact' ? (
        <ContactSupport onBack={handleBack} />
      ) : null}
    </div>
  );
}

