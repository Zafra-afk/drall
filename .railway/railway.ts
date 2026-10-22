import { defineRailway, project, service } from "railway/iac";

export const partial = "noble-flexibility";

export default defineRailway(() => {
  const nobleFlexibility = service("noble-flexibility", {
    build: {
      buildEnvironment: "V3",
      builder: "DOCKERFILE",
      dockerfilePath: "Dockerfile",
    },
    healthcheck: "/health",
    healthcheckTimeout: 300,
    preDeploy: "php artisan optimize:clear && php artisan migrate --force",
    replicas: { iad: 1 },
    deploy: { ipv6EgressEnabled: true },
  });

  return project("Drall", {
    variables: { managed: false },
    resources: [nobleFlexibility],
  });
});
