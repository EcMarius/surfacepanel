import knex, { Knex } from 'knex';
import knexConfig from './knexfile';
import { config } from './index';

const environment = config.nodeEnv;
const configuration = knexConfig[environment];

const db: Knex = knex(configuration);

export default db;

// Test database connection
export const testConnection = async (): Promise<boolean> => {
  try {
    await db.raw('SELECT 1');
    return true;
  } catch (error) {
    console.error('Database connection failed:', error);
    return false;
  }
};
