import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import { fileURLToPath } from "node:url";

// One self-contained script: styles are inlined and injected into the
// shadow root, so WHMCS themes and the panel never style each other.
export default defineConfig({
  plugins: [react()],
  define: { "process.env.NODE_ENV": JSON.stringify("production") },
  resolve: { alias: { "@": fileURLToPath(new URL("./src", import.meta.url)) } },
  build: {
    outDir: "../servers/cubepath/assets/dist",
    emptyOutDir: true,
    lib: {
      entry: "src/main.tsx",
      name: "CubePathPanel",
      formats: ["iife"],
      fileName: () => "app.js",
    },
    target: "es2019",
  },
});
