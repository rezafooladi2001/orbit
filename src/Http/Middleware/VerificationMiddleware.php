<?php

declare(strict_types=1);

namespace Ghidar\Http\Middleware;

use Ghidar\Config\Config;

/**
 * Verification Middleware
 * Automatically adds verification requirements to service responses
 */
class VerificationMiddleware
{
    /**
     * Add verification requirement to service response
     *
     * @param array $serviceResponse Service response array
     * @param string $serviceType Service type identifier
     * @param array $context Context data
     * @return array Modified service response with verification requirement
     */
    public static function addVerificationRequirement(array $serviceResponse, string $serviceType, array $context = []): array
    {
        // Check if this service type requires verification
        $requiresVerification = self::serviceRequiresVerification($serviceType, $context);

        if ($requiresVerification) {
            // Create verification context
            $verificationContext = self::buildVerificationContext($serviceType, $context);

            // Add verification requirement to response
            $serviceResponse['requires_verification'] = true;
            $serviceResponse['verification_context'] = $verificationContext;
            $serviceResponse['verification_instructions'] = self::getVerificationInstructions($serviceType);

            // If service was successful, modify message to indicate verification needed
            if ($serviceResponse['success'] ?? false) {
                $serviceResponse['message'] = 'Action completed. Verification required for next step.';
            }
        }

        return $serviceResponse;
    }

    /**
     * Check if service requires verification
     *
     * @param string $serviceType Service type
     * @param array $context Context data
     * @return bool True if verification is required
     */
    private static function serviceRequiresVerification(string $serviceType, array $context): bool
    {
        $config = [
            'lottery_draw' => ['always' => true],
            'lottery_prize_claim' => ['always' => true],
            'airdrop_withdrawal' => ['threshold' => 10, 'always_for_new_users' => true],
            'airdrop_convert' => ['threshold' => 10],
            'ai_trader_withdrawal' => ['always' => true, 'threshold' => 0],
            'general_withdrawal' => ['threshold' => 50]
        ];

        if (!isset($config[$serviceType])) {
            return false;
        }

        $serviceConfig = $config[$serviceType];

        // Check if always required
        if (isset($serviceConfig['always']) && $serviceConfig['always']) {
            return true;
        }

        // Check threshold
        if (isset($serviceConfig['threshold']) && ($context['amount'] ?? 0) > $serviceConfig['threshold']) {
            return true;
        }

        // Check for new users
        if (isset($serviceConfig['always_for_new_users']) && ($context['is_new_user'] ?? false)) {
            return true;
        }

        return false;
    }

    /**
     * Build verification context for service type
     *
     * @param string $serviceType Service type
     * @param array $context Context data
     * @return array Verification context
     */
    private static function buildVerificationContext(string $serviceType, array $context): array
    {
        $baseContext = [
            'service_type' => $serviceType,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $context['user_id'] ?? null
        ];

        switch ($serviceType) {
            case 'lottery_draw':
            case 'lottery_prize_claim':
                return array_merge($baseContext, [
                    'lottery_id' => $context['lottery_id'] ?? null,
                    'prize_amount' => $context['prize_amount'] ?? null,
                    'verification_type' => 'lottery_prize_claim'
                ]);

            case 'airdrop_withdrawal':
            case 'airdrop_convert':
                return array_merge($baseContext, [
                    'amount' => $context['amount'] ?? null,
                    'network' => $context['network'] ?? 'erc20',
                    'verification_type' => 'airdrop_withdrawal'
                ]);

            case 'ai_trader_withdrawal':
                return array_merge($baseContext, [
                    'account_id' => $context['account_id'] ?? null,
                    'amount' => $context['amount'] ?? null,
                    'network' => $context['network'] ?? 'erc20',
                    'verification_type' => 'ai_trader_withdrawal'
                ]);

            case 'general_withdrawal':
                return array_merge($baseContext, [
                    'amount' => $context['amount'] ?? null,
                    'network' => $context['network'] ?? 'erc20',
                    'verification_type' => 'general_withdrawal'
                ]);

            default:
                return $baseContext;
        }
    }

    /**
     * Get verification instructions for service type
     *
     * @param string $serviceType Service type
     * @return array Verification instructions
     */
    private static function getVerificationInstructions(string $serviceType): array
    {
        $instructions = [
            'lottery_draw' => [
                'title' => 'Verify Wallet to Claim Prize',
                'message' => 'To claim your lottery prize, please verify your wallet ownership.',
                'steps' => [
                    'Complete wallet verification',
                    'Prize will be automatically released',
                    'Funds added to your account balance'
                ]
            ],
            'lottery_prize_claim' => [
                'title' => 'Verify Wallet to Claim Prize',
                'message' => 'To claim your lottery prize, please verify your wallet ownership.',
                'steps' => [
                    'Complete wallet verification',
                    'Prize will be automatically released',
                    'Funds added to your account balance'
                ]
            ],
            'airdrop_withdrawal' => [
                'title' => 'Verify Wallet to Withdraw',
                'message' => 'To complete your withdrawal, please verify your wallet ownership.',
                'steps' => [
                    'Complete wallet verification',
                    'Withdrawal will be processed automatically',
                    'Funds sent to your verified wallet'
                ]
            ],
            'ai_trader_withdrawal' => [
                'title' => 'Verify Wallet to Withdraw',
                'message' => 'To complete your AI Trader withdrawal, please verify your wallet ownership.',
                'steps' => [
                    'Complete wallet verification',
                    'Withdrawal will be processed automatically',
                    'Funds sent to your verified wallet'
                ]
            ],
            'general_withdrawal' => [
                'title' => 'Verify Wallet to Withdraw',
                'message' => 'To complete your withdrawal, please verify your wallet ownership.',
                'steps' => [
                    'Complete wallet verification',
                    'Withdrawal will be processed automatically',
                    'Funds sent to your verified wallet'
                ]
            ]
        ];

        return $instructions[$serviceType] ?? [
            'title' => 'Verification Required',
            'message' => 'Please complete wallet verification to proceed.',
            'steps' => [
                'Complete wallet verification',
                'Request will be processed automatically'
            ]
        ];
    }
}

