/** @type {import('@stryker-mutator/api/core').PartialStrykerOptions} */
export default {
  packageManager: "npm",
  reporters: ["html", "clear-text", "progress"],
  testRunner: "vitest",
  vitest: {
    configFile: "vitest.config.js"
  },
  coverageAnalysis: "perTest",
  mutate: [
    "src/**/*.{js,ts,vue}",
    "!src/tests/**",
    "!src/**/*.spec.{js,ts}",
    "!src/**/*.test.{js,ts}",
    "!src/**/*.d.ts"
  ]
};
