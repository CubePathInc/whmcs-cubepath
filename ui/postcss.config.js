// WHMCS admin themes set html { font-size: 10px }, so rem units would shrink
// the panel there. Everything is converted to px against a 16px base.
const remToPx = () => ({
  postcssPlugin: "rem-to-px",
  Declaration(decl) {
    if (decl.value.includes("rem")) {
      decl.value = decl.value.replace(/(-?\d*\.?\d+)rem\b/g, (_, n) => `${parseFloat(n) * 16}px`);
    }
  },
});
remToPx.postcss = true;

export default {
  plugins: [(await import("tailwindcss")).default, remToPx, (await import("autoprefixer")).default],
};
