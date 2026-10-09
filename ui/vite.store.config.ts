import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { fileURLToPath } from "node:url";

// The order form cards, built separately from the panel so the cart page
// does not load the panel's charts. Runs after the panel build, which
// empties the output directory.
export default defineConfig({
  plugins: [react()],
  define: { "process.env.NODE_ENV": JSON.stringify("production") },
  resolve: { alias: { "@": fileURLToPath(new URL("./src", import.meta.url)) } },
  build: {
    outDir: "../servers/cubepath/assets/dist",
    emptyOutDir: false,
    lib: {
      entry: "src/store.tsx",
      name: "CubePathStore",
      formats: ["iife"],
      fileName: () => "store.js",
    },
    target: "es2019",
  },
});
