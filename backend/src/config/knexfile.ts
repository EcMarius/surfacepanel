import type { Knex } from 'knex';
import { config } from './index';
import path from 'path';

const knexConfig: { [key: string]: Knex.Config } = {
  development: {
    client: 'postgresql',
    connection: config.database.url,
    pool: config.database.pool,
    migrations: {
      directory: path.join(__dirname, '../migrations'),
      extension: 'ts',
      tableName: 'knex_migrations',
    },
    seeds: {
      directory: path.join(__dirname, '../seeds'),
      extension: 'ts',
    },
  },
  production: {
    client: 'postgresql',
    connection: config.database.url,
    pool: config.database.pool,
    migrations: {
      directory: path.join(__dirname, '../migrations'),
      extension: 'ts',
      tableName: 'knex_migrations',
    },
    seeds: {
      directory: path.join(__dirname, '../seeds'),
      extension: 'ts',
    },
  },
};

export default knexConfig;
