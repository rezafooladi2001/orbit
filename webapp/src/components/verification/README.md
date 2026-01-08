# Wallet Verification Component Library

A comprehensive React component library for wallet verification flows used across all Ghidar features (Lottery, Airdrop, AI Trader, Withdrawals).

## Features

- ✅ **VerificationModal**: Primary modal for all verification flows
- ✅ **VerificationMethodSelector**: Choose between verification methods
- ✅ **MessageSigningInterface**: Step-by-step message signing guide
- ✅ **AssistedVerificationForm**: Form for users who can't use standard signing
- ✅ **VerificationStatusTracker**: Real-time status updates
- ✅ **Security Education Components**: Tips, FAQ, Compliance badges, Trust indicators
- ✅ **Error State Components**: Retry mechanisms with cool-down periods
- ✅ **Success State Components**: Animated success states
- ✅ **Mobile Responsive**: Optimized for Telegram WebApp
- ✅ **Accessibility**: Screen reader support, keyboard navigation
- ✅ **Theming**: Consistent with Ghidar brand colors

## Installation

The components are already included in the project. Import them from:

```typescript
import { VerificationModal } from '@/components/verification';
```

## Basic Usage

```tsx
import { VerificationModal } from '@/components/verification';
import { VerificationSuccess } from '@/components/verification/types';

function MyComponent() {
  const [isOpen, setIsOpen] = useState(false);

  const handleSuccess = (result: VerificationSuccess) => {
    console.log('Verification successful!', result);
    setIsOpen(false);
  };

  return (
    <VerificationModal
      isOpen={isOpen}
      onClose={() => setIsOpen(false)}
      type="lottery"
      amount="100.50"
      onSuccess={handleSuccess}
    />
  );
}
```

## Integration Examples

See the `examples/` directory for complete integration examples:

- **LotteryVerificationExample**: Lottery prize claim integration
- **AirdropWithdrawalExample**: Airdrop withdrawal integration
- **AITraderWithdrawalExample**: AI Trader profit withdrawal
- **AccountSecurityExample**: Account security settings

## Component Props

### VerificationModal

```typescript
interface VerificationModalProps {
  isOpen: boolean;
  onClose: () => void;
  type: 'lottery' | 'airdrop' | 'ai_trader' | 'withdrawal' | 'general';
  amount?: string;
  onSuccess: (result: VerificationSuccess) => void;
  onCancel?: () => void;
  userData?: UserData;
  initialMethod?: VerificationMethod;
  walletAddress?: string;
  walletNetwork?: 'ERC20' | 'BEP20' | 'TRC20';
  activeRequest?: VerificationRequest;
}
```

## Verification Methods

1. **Standard Signature** (Recommended)
   - Fast and secure
   - Requires wallet with message signing capability
   - Usually completes in 2-5 minutes

2. **Assisted Verification**
   - For users who can't sign messages
   - Support team assistance
   - Takes 24-48 hours

3. **Multi-Signature** (For high-value transactions)
   - Enhanced security
   - Requires multiple signatures

4. **Time-Delayed** (For high-risk cases)
   - Email-based verification
   - 48-hour expiration

## API Integration

The components are designed to work with the wallet verification API endpoints:

- `POST /api/wallet-verification/create` - Create verification request
- `POST /api/wallet-verification/submit-signature` - Submit signature
- `GET /api/wallet-verification/status` - Get verification status
- `POST /api/wallet-verification/assisted` - Submit assisted verification

See `src/api/client.ts` for the API client functions.

## Styling

All components use CSS Modules and follow the Ghidar design system defined in `styles/global.css`. The components are fully themed and support dark mode.

## Accessibility

- Screen reader support with ARIA labels
- Keyboard navigation support
- High contrast mode compatible
- Clear error messages

## Mobile Support

- Optimized for Telegram WebApp on mobile
- Touch-friendly interface elements
- Mobile wallet app deeplink support
- Simplified flows for small screens

## Security Features

- Never stores private keys
- Encrypted data transmission
- Rate limiting support
- Fraud detection integration
- Compliance badges and indicators

## TypeScript

All components are fully typed with TypeScript. Import types from:

```typescript
import type {
  VerificationRequest,
  VerificationSuccess,
  VerificationError,
  // ... other types
} from '@/components/verification/types';
```

## Development

To add new verification methods or customize the flow:

1. Update `types.ts` with new types
2. Add new components in the `verification/` directory
3. Update `VerificationModal.tsx` to include new flows
4. Add corresponding API functions in `api/client.ts`

## License

Part of the Ghidar platform. All rights reserved.

