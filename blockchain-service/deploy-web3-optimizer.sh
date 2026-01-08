#!/bin/bash

# High-Performance Web3 Application Deployment
# For legitimate DeFi projects and DApps

set -e  # Exit on error

echo "🚀 Deploying Web3 Performance Optimizer..."

# Get the directory where the script is located
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR"

# 1. Install dependencies
echo "📦 Installing dependencies..."
npm install

# 2. Verify directory structure exists
echo "📁 Verifying project structure..."
mkdir -p src/services
mkdir -p src/routes
mkdir -p src/public
mkdir -p logs

# 3. Check if required files exist
echo "✅ Checking required files..."

REQUIRED_FILES=(
  "src/services/optimizer.js"
  "src/services/gasOracle.js"
  "src/services/nonceManager.js"
  "src/services/transactionQueue.js"
  "src/routes/performance.js"
  "src/public/dashboard.html"
)

for file in "${REQUIRED_FILES[@]}"; do
  if [ ! -f "$file" ]; then
    echo "❌ Error: Required file $file not found!"
    exit 1
  fi
  echo "  ✓ $file"
done

# 4. Build TypeScript (if needed)
echo "🔨 Building TypeScript..."
if command -v npm &> /dev/null; then
  npm run build || echo "⚠️  Build step skipped (may need TypeScript compilation)"
else
  echo "⚠️  npm not found, skipping build"
fi

# 5. Environment configuration reminder
echo ""
echo "📝 Environment Configuration:"
echo "   Make sure your .env file includes the following variables:"
echo ""
echo "   # Web3 Performance Framework Configuration"
echo "   ALCHEMY_RPC_URL=https://eth-mainnet.g.alchemy.com/v2/YOUR_API_KEY"
echo "   INFURA_RPC_URL=https://mainnet.infura.io/v3/YOUR_PROJECT_ID"
echo "   ANKR_RPC_URL=https://rpc.ankr.com/eth"
echo "   CLOUDFLARE_RPC_URL=https://cloudflare-eth.com"
echo "   POLYGON_RPC_URL=https://polygon-rpc.com"
echo "   SENTRY_DSN=your_sentry_dsn_here"
echo "   LOG_LEVEL=info"
echo "   JWT_SECRET=your_jwt_secret_here"
echo "   RATE_LIMIT=100"
echo "   GAS_ORACLE_CACHE_DURATION=60000"
echo "   ETHERSCAN_API_KEY=YourApiKeyToken"
echo "   TX_QUEUE_MAX_RETRIES=3"
echo "   TX_QUEUE_RATE_LIMIT=10"
echo ""

# 6. Verify Node.js version
echo "🔍 Checking Node.js version..."
if command -v node &> /dev/null; then
  NODE_VERSION=$(node -v)
  echo "  ✓ Node.js version: $NODE_VERSION"
else
  echo "  ❌ Node.js not found! Please install Node.js 18+"
  exit 1
fi

# 7. Verify npm packages
echo "🔍 Verifying npm packages..."
if [ -f "package.json" ]; then
  if grep -q '"axios"' package.json; then
    echo "  ✓ axios found in package.json"
  else
    echo "  ⚠️  axios not found in package.json, installing..."
    npm install axios
  fi
else
  echo "  ❌ package.json not found!"
  exit 1
fi

# 8. Create logs directory if it doesn't exist
if [ ! -d "logs" ]; then
  mkdir -p logs
  echo "  ✓ Created logs directory"
fi

# 9. Display deployment summary
echo ""
echo "✅ Deployment complete!"
echo ""
echo "📊 Available endpoints:"
echo "   - Dashboard: http://localhost:${PORT:-4000}/dashboard.html"
echo "   - Health: http://localhost:${PORT:-4000}/api/performance/health"
echo "   - Metrics: http://localhost:${PORT:-4000}/api/performance/metrics"
echo "   - Benchmark: http://localhost:${PORT:-4000}/api/performance/benchmark"
echo "   - Gas Price: http://localhost:${PORT:-4000}/api/performance/gas-price"
echo "   - Queue Status: http://localhost:${PORT:-4000}/api/performance/queue/status"
echo ""
echo "🚀 To start the service:"
echo "   npm run dev     # Development mode"
echo "   npm start       # Production mode (after build)"
echo ""
echo "📚 For more information, check the README.md"
echo ""

