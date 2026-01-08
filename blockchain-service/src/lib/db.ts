/**
 * Database connection module for blockchain-service.
 * Provides MySQL connection pool for reading deposit addresses and updating deposit status.
 */

import mysql from 'mysql2/promise';
import { Config } from '../config';

let pool: mysql.Pool | null = null;

/**
 * Initialize database connection pool.
 */
export function initDb(config: Config['db']): void {
  pool = mysql.createPool({
    host: config.host,
    port: config.port,
    database: config.database,
    user: config.username,
    password: config.password,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0,
    charset: 'utf8mb4',
  });
}

/**
 * Get database connection pool.
 */
export function getDb(): mysql.Pool {
  if (!pool) {
    throw new Error('Database pool not initialized. Call initDb() first.');
  }
  return pool;
}

/**
 * Close database connection pool.
 */
export async function closeDb(): Promise<void> {
  if (pool) {
    await pool.end();
    pool = null;
  }
}

