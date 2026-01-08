Stack & Style

Use React + TypeScript.

Function components + hooks.

State management:

Start with React Query / simple hooks; avoid heavy libraries unless really needed.

Use a design system:

Consistent spacing, font sizes, and colors.

Dark theme with gold accents for Ghidar brand.

Structure

Suggested file structure:

src/
  api/
  components/
  features/
    home/
    lottery/
    airdrop/
    aiTrader/
  hooks/
  lib/
  styles/
  types/


Each feature (Lottery, Airdrop, AI Trader) should have:

pages, components, hooks, types.

UI/UX Rules

The first screen after login shows three big cards:

Lottery

Airdrop (GHD Miner)

AI Trader

All screens must:

Be mobile-first (Telegram mini apps mostly on mobile).

Have clear CTAs (one main action per screen).

Use Telegram WebApp APIs for:

Theme detection.

Closing the app if needed.

Top bar colors if appropriate.

API Usage

Encapsulate API calls in reusable functions under src/api/.

For each API:

Define TypeScript types for request/response.

Handle errors gracefully (show error toast or message, not raw errors).