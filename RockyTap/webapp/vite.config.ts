import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig(({ mode }) => ({
  plugins: [
    react({
      // Enable React fast refresh for better dev experience
      fastRefresh: true,
    }),
  ],
  resolve: {
    alias: {
      '@': resolve(__dirname, 'src'),
    },
  },
  build: {
    outDir: '../assets/ghidar',
    emptyOutDir: true,
    // Target modern browsers for smaller bundle
    target: ['es2020', 'edge88', 'firefox78', 'chrome87', 'safari14'],
    // Use esbuild for minification (built-in, no extra dependencies)
    minify: 'esbuild',
    // Enable source maps for production debugging (optional)
    sourcemap: mode === 'production' ? false : true,
    // Chunk size warning limit
    chunkSizeWarningLimit: 500,
    rollupOptions: {
      output: {
        // Manual chunk splitting for better caching
        manualChunks: (id) => {
          // Vendor chunks
          if (id.includes('node_modules')) {
            // React core
            if (id.includes('react') || id.includes('react-dom')) {
              return 'vendor-react';
            }
            // Icons (lucide-react is often large) - separate chunk for caching
            if (id.includes('lucide-react')) {
              return 'vendor-icons';
            }
            // QR code library
            if (id.includes('qrcode')) {
              return 'vendor-qrcode';
            }
            // All other vendor code
            return 'vendor';
          }
          // Verification components - separate lazy chunk (heavy)
          if (id.includes('/components/verification/')) {
            return 'verification';
          }
          // Help components - separate lazy chunk
          if (id.includes('/components/help/')) {
            return 'help';
          }
          // Settings components - separate lazy chunk
          if (id.includes('/components/settings/')) {
            return 'settings';
          }
          // Onboarding components
          if (id.includes('/components/onboarding/')) {
            return 'onboarding';
          }
          // Screen components - lazy loaded
          if (id.includes('/screens/')) {
            return 'screens';
          }
          // Component library
          if (id.includes('/components/ui/')) {
            return 'ui-components';
          }
        },
        entryFileNames: 'index.js',
        chunkFileNames: (chunkInfo) => {
          // Add hash for cache busting in production
          if (mode === 'production') {
            return '[name]-[hash].js';
          }
          return '[name].js';
        },
        assetFileNames: (assetInfo) => {
          // Handle CSS and other assets
          if (assetInfo.name?.endsWith('.css')) {
            return mode === 'production' ? 'styles-[hash].[ext]' : 'styles.[ext]';
          }
          return '[name].[ext]';
        },
      },
      // Tree shaking optimizations
      treeshake: {
        moduleSideEffects: 'no-external',
        propertyReadSideEffects: false,
        tryCatchDeoptimization: false,
      },
    },
    // Report compressed size
    reportCompressedSize: true,
    // CSS code splitting
    cssCodeSplit: true,
  },
  // CSS optimizations
  css: {
    devSourcemap: true,
    modules: {
      // Shorter class names in production
      generateScopedName: mode === 'production' 
        ? '[hash:base64:6]' 
        : '[name]__[local]',
    },
  },
  // Optimize dependencies
  optimizeDeps: {
    include: ['react', 'react-dom', 'lucide-react'],
    // Exclude large optional dependencies
    exclude: [],
  },
  // Esbuild optimizations
  esbuild: {
    // Drop console in production
    drop: mode === 'production' ? ['console', 'debugger'] : [],
    // Legal comments
    legalComments: 'none',
  },
  // Define environment variables for production build
  // Note: VITE_OFFLINE_CACHE removed - was causing mock data in production
  define: {},
  // Use root path in dev mode for easier local development
  // Use production path in build mode
  base: mode === 'development' ? '/' : '/RockyTap/assets/ghidar/',
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: false,
  },
  // Preview server config (for testing production builds locally)
  preview: {
    port: 4173,
    strictPort: false,
  },
}));
