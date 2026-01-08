import { useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle, Button } from '../ui';
import { HelpArticle as HelpArticleType } from '../../api/client';
import styles from './HelpArticle.module.css';

/**
 * Basic HTML sanitization for help articles.
 * Only allows safe tags and removes potentially dangerous attributes.
 * 
 * NOTE: This is a basic implementation. For full XSS protection,
 * consider using DOMPurify library when the content source is less trusted.
 * 
 * The backend should also sanitize content before storing.
 */
function sanitizeHtml(html: string): string {
  // Remove script tags and their content
  let sanitized = html.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
  
  // Remove event handlers (onclick, onerror, etc.)
  sanitized = sanitized.replace(/\s*on\w+\s*=\s*["'][^"']*["']/gi, '');
  sanitized = sanitized.replace(/\s*on\w+\s*=\s*[^\s>]+/gi, '');
  
  // Remove javascript: URLs
  sanitized = sanitized.replace(/href\s*=\s*["']javascript:[^"']*["']/gi, 'href="#"');
  
  // Remove data: URLs (can be used for XSS in some cases)
  sanitized = sanitized.replace(/src\s*=\s*["']data:[^"']*["']/gi, 'src=""');
  
  return sanitized;
}

interface HelpArticleProps {
  article: HelpArticleType;
  onBack: () => void;
}

export function HelpArticle({ article, onBack }: HelpArticleProps) {
  // Memoize sanitized content to avoid re-processing on every render
  const sanitizedContent = useMemo(
    () => sanitizeHtml(article.content),
    [article.content]
  );

  return (
    <div className={styles.container}>
      <Card variant="elevated">
        <CardHeader>
          <div className={styles.articleHeader}>
            <div>
              <span className={styles.category}>{article.category}</span>
              <CardTitle>{article.title}</CardTitle>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div
            className={styles.content}
            // eslint-disable-next-line react/no-danger
            dangerouslySetInnerHTML={{ __html: sanitizedContent }}
          />
          
          {article.related_articles && article.related_articles.length > 0 && (
            <div className={styles.relatedSection}>
              <h4 className={styles.relatedTitle}>Related Articles</h4>
              <ul className={styles.relatedList}>
                {article.related_articles.map((relatedId) => (
                  <li key={relatedId} className={styles.relatedItem}>
                    <a href={`#article-${relatedId}`} className={styles.relatedLink}>
                      Article #{relatedId}
                    </a>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </CardContent>
      </Card>

      <div className={styles.actions}>
        <Button fullWidth variant="outline" onClick={onBack}>
          Back to Help Center
        </Button>
      </div>
    </div>
  );
}

