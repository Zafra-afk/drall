import { defineRailway, mysql, preserve, project, redis, service, volume } from "railway/iac";

export default defineRailway(() => {
  const Redis = redis("Redis", { region: "iad" });
  Redis.deploy = { startCommand: "/bin/sh -c \"rm -rf $RAILWAY_VOLUME_MOUNT_PATH/lost+found/ && exec docker-entrypoint.sh redis-server --requirepass $REDIS_PASSWORD --save 60 1 --dir $RAILWAY_VOLUME_MOUNT_PATH\"" };
  Redis.networking = { privateNetworkEndpoint: "redis" };
  const MySQL = mysql("MySQL", { region: "iad" });
  MySQL.deploy = { startCommand: "docker-entrypoint.sh mysqld --innodb-use-native-aio=0 --disable-log-bin --performance_schema=0 --innodb-buffer-pool-size=1G" };
  MySQL.networking = { privateNetworkEndpoint: "mysql" };
  const mysqlVolume = volume("mysql-volume", { alerts: { usage: { "100": {}, "80": {}, "95": {} } }, allowOnlineResize: true, region: "iad", sizeMB: 500 });
  const redisVolume = volume("redis-volume", { alerts: { usage: { "100": {}, "80": {}, "95": {} } }, allowOnlineResize: true, region: "iad", sizeMB: 500 });
  const nobleFlexibility = service("noble-flexibility", {
    build: { buildEnvironment: "V3", builder: "RAILPACK" },
    healthcheck: "/",
    replicas: { "iad": 1 },
    deploy: { ipv6EgressEnabled: true },
    cdn: { enabled: true },
    env: { APP_CURRENCY: preserve(), APP_DEBUG: preserve(), APP_ENV: preserve(), APP_FALLBACK_LOCALE: preserve(), APP_KEY: preserve(), APP_LOCALE: preserve(), APP_NAME: preserve(), APP_TIMEZONE: preserve(), APP_URL: preserve(), CACHE_STORE: preserve(), DATABASE_URL: preserve(), DB_CONNECTION: preserve(), DB_DATABASE: preserve(), DB_HOST: preserve(), DB_HOST_TEST: preserve(), DB_PASSWORD: preserve(), DB_PORT: preserve(), DB_PREFIX: preserve(), DB_USERNAME: preserve(), DRALL_ADMIN_EMAIL: preserve(), DRALL_ADMIN_NAME: preserve(), DRALL_ADMIN_PASSWORD: preserve(), EXCHANGE_RATES_API_KEY: preserve(), LOG_LEVEL: preserve(), LOG_STACK: preserve(), MYSQL_DATABASE: preserve(), MYSQL_HOST: preserve(), MYSQL_PASSWORD: preserve(), MYSQL_PORT: preserve(), MYSQL_USER: preserve(), QUEUE_CONNECTION: preserve(), REDIS_CLIENT: preserve(), REDIS_HOST: preserve(), REDIS_PASSWORD: preserve(), REDIS_PORT: preserve(), REDIS_URL: preserve(), SCOUT_DRIVER: preserve(), SESSION_DRIVER: preserve(), SESSION_SECURE_COOKIE: preserve(), TEST_BRACES: preserve(), TEST_ESCAPED: preserve(), TEST_TEMPLATE: preserve(), TEST_TEMPLATE2: preserve(), TEST_VAR: preserve(), TRUSTED_PROXIES: preserve(), VITE_APP_NAME: preserve() },
  });

  return project("Drall", {
    variables: { managed: true },
    resources: [Redis, MySQL, nobleFlexibility, mysqlVolume, redisVolume],
  });
});
